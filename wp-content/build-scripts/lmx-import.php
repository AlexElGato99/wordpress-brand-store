<?php
/** CLI-only, resumable import of the verified LMX public catalog snapshot. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
ini_set('memory_limit', '1024M');
set_time_limit(0);
require __DIR__ . '/lib.php';
const LMX_DIR = 'C:/xampp/boutique-migration';
function snapshot($name) { return json_decode(file_get_contents(LMX_DIR."/$name.json"), true, 512, JSON_THROW_ON_ERROR); }
function dec($s) { return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function lmx_checked($v) { if (is_wp_error($v)) throw new Exception($v->get_error_message()); return $v; }
$mode = $argv[1] ?? 'products';
$images = get_option('lmx_image_map', []);
for ($shard=0;$shard<4;$shard++) $images=array_replace($images,get_option('lmx_image_map_'.$shard,[]));
$mapping = get_option('lmx_product_map', []);
$products = snapshot('products');
$variations = snapshot('variations');
$categories = snapshot('categories');
if ($mode === 'status') {
    echo json_encode(['media_registered'=>count($images),'media_total'=>count(snapshot('images')),'products_and_variations'=>count($mapping)]),"\n";
    exit;
}

if ($mode === 'media') {
    $shard=(int)($argv[2] ?? 0);
    $shards=(int)($argv[3] ?? 1);
    $image_option=$shards>1 ? 'lmx_image_map_'.$shard : 'lmx_image_map';
    $done=0;
    if (snapshot('download-errors')) throw new Exception('Unresolved media download errors');
    add_filter('big_image_size_threshold', '__return_false');
    add_filter('intermediate_image_sizes_advanced', function($sizes) { return array_intersect_key($sizes, array_flip(['thumbnail','woocommerce_thumbnail','woocommerce_single'])); });
    $uploads = wp_upload_dir();
    $dir = $uploads['basedir'].'/lmx-catalog';
    wp_mkdir_p($dir);
    $all = snapshot('images');
    foreach ($all as $n => $im) {
        if ($n % $shards !== $shard) continue;
        $id = $images[$im['src']] ?? 0;
        if ($id && get_post($id) && get_post_meta($id,'_lmx_media_complete',true)) continue;
        $file = $dir.'/'.basename($im['file']);
        if (!file_exists($file)) { if (!copy($im['file'], $file)) throw new Exception('Cannot copy '.$file); }
        if (!$id || !get_post($id)) {
            $type = wp_check_filetype($file);
            $id = lmx_checked(wp_insert_attachment(['post_mime_type'=>$type['type'],'post_title'=>dec($im['name'] ?? ''),'post_status'=>'inherit'], $file, 0, true));
            $images[$im['src']] = $id;
            update_option($image_option,$images,false);
        }
        update_post_meta($id,'_wp_attachment_image_alt',dec($im['alt'] ?? ''));
        update_post_meta($id,'_lmx_source_url',$im['src']);
        update_post_meta($id,'_lmx_sha256',$im['sha256']);
        $metadata = wp_generate_attachment_metadata($id,$file);
        if (str_starts_with(get_post_mime_type($id),'image/') && empty($metadata['width'])) throw new Exception('Invalid image metadata '.$file);
        wp_update_attachment_metadata($id,$metadata);
        update_post_meta($id,'_lmx_media_complete',1);
        $done++;
        if ($done%50===0) { echo 'Media worker '.$shard.': '.$done." processed\n"; }
    }
    echo 'Media complete: '.count($images)."\n";
    exit;
}

function term_id($name,$slug,$taxonomy) {
    $term = get_term_by('slug',$slug,$taxonomy);
    if ($term) return (int)$term->term_id;
    return (int)lmx_checked(wp_insert_term(dec($name),$taxonomy,['slug'=>$slug]))['term_id'];
}
function local_html($html) {
    global $images, $mapping, $products;
    static $replace=null;
    if ($replace===null) {
        $replace=[];
        foreach ($images as $url=>$id) $replace[$url]=wp_get_attachment_url($id);
        foreach ($products as $p) if (isset($mapping[$p['id']])) $replace[$p['permalink']]=home_url('/product/'.$p['slug'].'/');
    }
    return strtr($html,$replace);
}
function price_minus_ten($p, $key) {
    $raw=$p['prices'][$key];
    // Store API represents absent prices as zero; keep unpurchasable items unpriced.
    if ($raw==='' || ((int)$raw===0 && !$p['is_purchasable'])) return '';
    $amount=((int)$raw)/(10 ** $p['prices']['currency_minor_unit']);
    if ($amount<10) throw new Exception('Price below EUR10 for '.$p['id']);
    return number_format($amount-10,2,'.','');
}
function apply_fields($obj,$p) {
    global $images;
    $obj->set_name(dec($p['name']));
    $obj->set_status('publish');
    $obj->set_slug($p['slug']);
    $obj->set_sku($p['sku']);
    $obj->set_description(local_html($p['description']));
    $obj->set_short_description(local_html($p['short_description']));
    $obj->set_sold_individually((bool)$p['sold_individually']);
    $obj->set_manage_stock(false);
    $obj->set_backorders($p['is_on_backorder'] ? 'yes' : 'no');
    $obj->set_stock_status($p['is_on_backorder'] ? 'onbackorder' : ($p['is_in_stock'] ? 'instock' : 'outofstock'));
    $obj->set_weight($p['weight']);
    foreach (['length','width','height'] as $dim) $obj->{'set_'.$dim}($p['dimensions'][$dim]);
    if ($p['type'] !== 'variable') {
        $obj->set_regular_price(price_minus_ten($p,'regular_price'));
        $obj->set_sale_price($p['on_sale'] ? price_minus_ten($p,'sale_price') : '');
    }
    $ids=[];
    foreach ($p['images'] as $im) {
        if (empty($images[$im['src']])) throw new Exception('Missing image '.$im['src']);
        $ids[]=(int)$images[$im['src']];
    }
    $obj->set_image_id($ids[0] ?? 0);
    if ($p['type']!=='variation') $obj->set_gallery_image_ids(array_slice($ids,1));
    $obj->update_meta_data('_lmx_source_id',$p['id']);
    $obj->update_meta_data('_lmx_source_url',$p['permalink']);
    $obj->update_meta_data('_lmx_source_prices',$p['prices']);
}

if ($mode === 'products') {
    global $wpdb;
    foreach ($wpdb->get_results("SELECT p.ID, m.meta_value AS source_id FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON p.ID=m.post_id WHERE m.meta_key='_lmx_source_id' AND p.post_type IN ('product','product_variation')") as $row) $mapping[(int)$row->source_id]=(int)$row->ID;
    update_option('lmx_product_map',$mapping,false);
    if (!file_exists(LMX_DIR.'/before-import.sql')) throw new Exception('Backup required');
    $coverage=snapshot('coverage');
    if ($coverage['missing_sitemap_urls']) throw new Exception('Incomplete catalog');
    if (count($images)!==count(snapshot('images'))) throw new Exception('Media import incomplete');
    wp_defer_term_counting(true);
    $cats=[];
    foreach ($categories as $c) $cats[$c['id']]=term_id($c['name'],$c['slug'],'product_cat');
    foreach ($categories as $c) {
        lmx_checked(wp_update_term($cats[$c['id']],'product_cat',['name'=>dec($c['name']),'description'=>$c['description'],'parent'=>$cats[$c['parent']] ?? 0]));
        if (!empty($c['image'])) update_term_meta($cats[$c['id']],'thumbnail_id',$images[$c['image']['src']]);
    }
    update_option('lmx_category_map',$cats,false);
    $seenattrs=[];
    foreach ($products as $p) foreach ($p['attributes'] as $a) if ($a['taxonomy']) {
        $tax=$a['taxonomy'];
        if (!wc_attribute_taxonomy_id_by_name($tax)) {
            lmx_checked(wc_create_attribute(['name'=>dec($a['name']),'slug'=>substr($tax,3),'type'=>'select','order_by'=>'menu_order','has_archives'=>false]));
            delete_transient('wc_attribute_taxonomies');
            WC_Cache_Helper::invalidate_cache_group('woocommerce-attributes');
        }
        if (!isset($seenattrs[$tax])) {
            lmx_checked(wc_update_attribute(wc_attribute_taxonomy_id_by_name($tax),['name'=>dec($a['name']),'slug'=>substr($tax,3),'type'=>'select','order_by'=>'menu_order','has_archives'=>false]));
            $seenattrs[$tax]=true;
        }
        if (!taxonomy_exists($tax)) register_taxonomy($tax,['product'],['hierarchical'=>false,'label'=>dec($a['name']),'public'=>false]);
        foreach ($a['terms'] as $t) term_id($t['name'],$t['slug'],$tax);
    }
    $by_parent=[];
    foreach ($variations as $v) $by_parent[$v['parent']][$v['id']]=$v;
    // Establish every destination URL before rewriting cross-product links in descriptions.
    foreach ($products as $p) if (empty($mapping[$p['id']])) {
        $class=$p['type']==='variable' ? 'WC_Product_Variable' : 'WC_Product_Simple';
        $stub=new $class();
        $stub->set_name(dec($p['name']));
        $stub->set_slug($p['slug']);
        $stub->set_status('draft');
        $stub->update_meta_data('_lmx_source_id',$p['id']);
        $mapping[$p['id']]=$stub->save();
        update_option('lmx_product_map',$mapping,false);
    }
    foreach ($products as $index=>$p) {
        if (isset($argv[2]) && $p['id']<(int)$argv[2]) continue;
        $id=$mapping[$p['id']] ?? 0;
        $class=$p['type']==='variable' ? 'WC_Product_Variable' : 'WC_Product_Simple';
        $obj=$id ? new $class($id) : new $class();
        apply_fields($obj,$p);
        $obj->set_catalog_visibility('visible');
        $obj->set_category_ids(array_map(fn($c)=>$cats[$c['id']],$p['categories']));
        $obj->set_tag_ids(array_map(fn($t)=>term_id($t['name'],$t['slug'],'product_tag'),$p['tags']));
        $attrs=[]; $defaults=[];
        foreach ($p['attributes'] as $pos=>$a) {
            $attr=new WC_Product_Attribute();
            $attr->set_id($a['taxonomy'] ? wc_attribute_taxonomy_id_by_name($a['taxonomy']) : 0);
            $attr->set_name($a['taxonomy'] ?: dec($a['name']));
            $attr->set_options(array_map(fn($t)=>$a['taxonomy'] ? term_id($t['name'],$t['slug'],$a['taxonomy']) : dec($t['name']),$a['terms']));
            $attr->set_position($pos);
            $attr->set_visible(true);
            $attr->set_variation($a['has_variations']);
            $attrs[]=$attr;
            foreach ($a['terms'] as $t) if (!empty($t['default'])) $defaults[sanitize_title($a['taxonomy'] ?: $a['name'])]=$a['taxonomy'] ? $t['slug'] : $t['name'];
        }
        $obj->set_attributes($attrs);
        if ($p['type']==='variable') $obj->set_default_attributes($defaults);
        $id=$obj->save();
        $mapping[$p['id']]=$id;
        update_option('lmx_product_map',$mapping,false);
        foreach ($p['variations'] as $ref) {
            $v=$by_parent[$p['id']][$ref['id']] ?? null;
            if (!$v) throw new Exception('Missing variation '.$ref['id']);
            $variation=new WC_Product_Variation($mapping[$v['id']] ?? 0);
            $variation->set_parent_id($id);
            apply_fields($variation,$v);
            parse_str(parse_url($v['permalink'],PHP_URL_QUERY) ?? '',$query);
            $va=[];
            foreach ($query as $key=>$value) if (str_starts_with($key,'attribute_')) $va[substr($key,10)]=$value;
            if (!$va) {
                foreach ($ref['attributes'] as $ra) foreach ($p['attributes'] as $a) if ($a['name']===$ra['name']) $va[sanitize_title($a['taxonomy'] ?: $a['name'])]=$ra['value'] ?? '';
            }
            if (!$va && array_filter($p['attributes'],fn($a)=>$a['has_variations'])) throw new Exception('Missing variation attributes '.$v['id']);
            $variation->set_attributes($va);
            $mapping[$v['id']]=$variation->save();
        }
        if ($p['type']==='variable') WC_Product_Variable::sync($id);
        update_option('lmx_product_map',$mapping,false);
        if (($index+1)%25===0) echo 'Products '.($index+1).'/'.count($products)."\n";
    }
    // Keep the existing Elementor category links/grids functional with matching catalog categories.
    $aliases=['men'=>['heren'],'women'=>['dames'],'shoes'=>['schoenen-heren','dames-schoenen'],'t-shirts'=>['t-shirts','polos'],'jackets'=>['jassen','dames-jassen'],'pants'=>['broeken'],'bags'=>['heren-tassen','dames-tassen'],'accessories'=>['accessoires','dames-accessoires']];
    $buildcats=bstate('cats') ?: [];
    foreach ($products as $p) {
        $slugs=array_column($p['categories'],'slug');
        foreach ($aliases as $alias=>$sources) if (isset($buildcats[$alias]) && array_intersect($slugs,$sources)) wp_set_object_terms($mapping[$p['id']],[(int)$buildcats[$alias]],'product_cat',true);
    }
    // Draft only known, generated demo products, preserving them for rollback.
    foreach ((bstate('products') ?: []) as $demo) if (get_post($demo) && !in_array($demo,$mapping)) wp_update_post(['ID'=>$demo,'post_status'=>'draft']);
    wp_defer_term_counting(false);
    wc_delete_product_transients();
    delete_transient('wc_products_onsale');
    if (class_exists('Elementor\\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
    flush_rewrite_rules(false);
    echo 'Catalog import complete: '.count($products).' products and '.count($variations)." variations\n";
}

if ($mode === 'verify') {
    $errors=[]; $unpriced=[];
    foreach (array_merge($products,$variations) as $p) {
        $id=$mapping[$p['id']] ?? 0; $obj=$id ? wc_get_product($id) : null;
        if (!$obj) { $errors[]='Missing '.$p['id']; continue; }
        if ($obj->get_status()!=='publish') $errors[]='Not published '.$p['id'];
        if ($obj->get_type()!==$p['type']) $errors[]='Type '.$p['id'];
        if ($p['is_purchasable'] && (float)$obj->get_price()!==(float)price_minus_ten($p,'price')) $errors[]='Effective price '.$p['id'];
        if ($p['type']!=='variation' && $obj->get_name()!==dec($p['name'])) $errors[]='Name '.$p['id'];
        if ($obj->get_description()!==local_html($p['description'])) $errors[]='Description '.$p['id'];
        if ($obj->get_short_description()!==local_html($p['short_description'])) $errors[]='Short description '.$p['id'];
        if ($p['type']!=='variable') {
            if ((string)$obj->get_regular_price()!==(string)price_minus_ten($p,'regular_price')) $errors[]='Regular price '.$p['id'];
            if ((string)$obj->get_sale_price()!==($p['on_sale'] ? price_minus_ten($p,'sale_price') : '')) $errors[]='Sale price '.$p['id'];
            if ($obj->get_price()==='') $unpriced[]=$p['id'];
        } else {
            if (count($obj->get_children())!==count($p['variations'])) $errors[]='Variation count '.$p['id'];
        }
        $expected=array_map(fn($im)=>(int)$images[$im['src']],$p['images']);
        if ((int)$obj->get_image_id()!==($expected[0] ?? 0)) $errors[]='Main image '.$p['id'];
        if ($p['type']!=='variation' && $obj->get_gallery_image_ids()!==array_slice($expected,1)) $errors[]='Gallery '.$p['id'];
        if ($obj->get_sku()!==$p['sku']) $errors[]='SKU '.$p['id'];
        if ($p['type']!=='variable' && $obj->is_in_stock()!==(bool)$p['is_in_stock']) $errors[]='Stock '.$p['id'];
        if ($p['type']==='variation' && $obj->get_parent_id()!==($mapping[$p['parent']] ?? 0)) $errors[]='Parent '.$p['id'];
        if ($p['type']!=='variation') {
            $catmap=get_option('lmx_category_map',[]);
            foreach ($p['categories'] as $c) if (!in_array($catmap[$c['id']],$obj->get_category_ids())) $errors[]='Category '.$p['id'].':'.$c['slug'];
            $actual=$obj->get_attributes();
            foreach ($p['attributes'] as $a) {
                $key=sanitize_title($a['taxonomy'] ?: $a['name']);
                if (!isset($actual[$key]) || count($actual[$key]->get_options())!==count($a['terms'])) $errors[]='Attribute '.$p['id'].':'.$key;
            }
        }
    }
    foreach (snapshot('images') as $im) {
        $file=get_attached_file($images[$im['src']] ?? 0);
        if (!$file || !file_exists($file) || hash_file('sha256',$file)!==$im['sha256']) $errors[]='Image checksum '.$im['src'];
    }
    $issues=[];
    foreach ($products as $p) {
        if ((int)$p['prices']['price']===0 && !$p['is_purchasable']) $issues[]=['source_id'=>$p['id'],'name'=>dec($p['name']),'issue'=>'Source has no usable price; kept unpriced'];
        if ($p['type']==='variable' && !$p['attributes']) $issues[]=['source_id'=>$p['id'],'name'=>dec($p['name']),'issue'=>'Source has variations without size/color attributes; preserved unchanged'];
    }
    $media_types=['images'=>0,'videos'=>0];
    foreach (snapshot('images') as $im) $media_types[str_ends_with($im['file'],'.mp4') ? 'videos' : 'images']++;
    $report=['products'=>count($products),'variations'=>count($variations),'media'=>$media_types,'source_unpriced_items'=>$unpriced,'source_issues'=>$issues,'errors'=>$errors,'verified_at'=>gmdate('c')];
    file_put_contents(LMX_DIR.'/verification.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),"\n";
    exit($errors ? 1 : 0);
}
