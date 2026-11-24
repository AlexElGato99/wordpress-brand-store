<?php
/** Install generated WebP banners without rebuilding pages or altering the home hero. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_ACCESSIBLE_HOSTS', 'localhost,127.0.0.1');
define('DISABLE_WP_CRON', true);
require __DIR__ . '/lib.php';
set_time_limit(0);
$report = json_decode(file_get_contents('D:/boutique-migration-work/banner-assets.json'), true, 512, JSON_THROW_ON_ERROR);
$state = get_option('boutique_build_state', []);
$pageKeys = [170=>'sale',165=>'about',166=>'faq',167=>'shipping',168=>'returns',169=>'contact',171=>'legal',10=>'cart',11=>'checkout',12=>'account'];
$pageIds = array_merge([172], array_keys($pageKeys));
$backupFile = 'D:/boutique-migration-work/banner-before.json';
if (!file_exists($backupFile)) {
    $before = ['state'=>$state, 'pages'=>[]];
    foreach ($pageIds as $id) $before['pages'][$id] = get_post_meta($id, '_elementor_data', true);
    file_put_contents($backupFile, wp_json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
$homeBefore = json_decode(get_post_meta(172, '_elementor_data', true), true, 512, JSON_THROW_ON_ERROR);
$originalHero = wp_json_encode($homeBefore[0]);
$ids = [];
foreach ($report as $asset) {
    $key = $asset['key']; $path = $asset['file'];
    if (!is_file($path) || mime_content_type($path) !== 'image/webp') throw new Exception('Missing WebP: '.$path);
    $id = (int)($state['generated_banner_'.$key] ?? 0);
    if (!$id || !get_post($id)) {
        $id = wp_insert_attachment(['post_title'=>'Section banner – '.str_replace('_',' ', $key), 'post_mime_type'=>'image/webp', 'post_status'=>'inherit'], $path, 0, true);
        if (is_wp_error($id)) throw new Exception($id->get_error_message());
        $meta = wp_generate_attachment_metadata($id, $path);
        wp_update_attachment_metadata($id, $meta);
    }
    $ids[$key] = $id; $state['generated_banner_'.$key] = $id;
}
update_option('boutique_build_state', $state, false);
$tiles = ['f431404'=>'shoes','f718d36'=>'shirts','1ca257a'=>'jackets','6edc112'=>'pants','312e7fa'=>'bags','11167e0'=>'accessories'];
function update_tiles(&$elements, $tiles, $ids, &$found) {
    foreach ($elements as &$node) {
        if (isset($tiles[$node['id'] ?? ''])) {
            $key = $tiles[$node['id']]; $id = $ids[$key];
            $node['settings']['background_image'] = ['url'=>wp_get_attachment_url($id),'id'=>$id,'source'=>'library','alt'=>''];
            $node['settings']['background_position'] = in_array($key, ['pants','accessories'], true) ? 'left center' : 'right center';
            $found[] = $key;
        }
        if (!empty($node['elements'])) update_tiles($node['elements'], $tiles, $ids, $found);
    }
}
$found=[]; update_tiles($homeBefore, $tiles, $ids, $found);
if (count($found)!==count($tiles) || wp_json_encode($homeBefore[0]) !== $originalHero) throw new Exception('Home hero or tile preflight failed');
update_post_meta(172, '_elementor_data', wp_slash(wp_json_encode($homeBefore)));
foreach ($pageKeys as $id=>$key) {
    $data=json_decode(get_post_meta($id, '_elementor_data', true), true, 512, JSON_THROW_ON_ERROR);
    if (empty($data[0]['id'])) throw new Exception('Missing header for page '.$id);
    $settings = &$data[0]['settings'];
    $settings['css_classes'] = trim(($settings['css_classes'] ?? '').' btq-generated-banner');
    $settings['background_background'] = 'classic';
    $settings['background_image'] = ['url'=>wp_get_attachment_url($ids[$key]),'id'=>$ids[$key],'source'=>'library','alt'=>''];
    $settings['background_position'] = 'center center'; $settings['background_size']='cover';
    unset($settings);
    update_post_meta($id, '_elementor_data', wp_slash(wp_json_encode($data)));
}
$after = json_decode(get_post_meta(172, '_elementor_data', true), true, 512, JSON_THROW_ON_ERROR);
if (wp_json_encode($after[0]) !== $originalHero) throw new Exception('Home hero was changed');
if (class_exists('\\Elementor\\Plugin')) \Elementor\Plugin::$instance->files_manager->clear_cache();
wp_cache_flush();
echo wp_json_encode(['assets'=>count($ids),'updated_pages'=>count($pageKeys),'home_tiles'=>count($found),'home_hero_unchanged'=>true,'ids'=>$ids], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
