<?php
/**
 * Plugin Name: LMX Product Details
 * Description: Product-page content from the old LMX site in the new style: stock line + "last item" badges,
 *              shoe size guide, recently viewed products and gallery arrows.
 */
if (!defined('ABSPATH')) exit;

function lmx_is_last_item($product_id) { return (bool)get_post_meta($product_id, '_lmx_last_item', true); }

/* ---------- "Last item" badge on product cards (old site: LAATSTE STUK) ---------- */
add_action('astra_woo_shop_price_after', function () {
    global $product;
    if ($product && $product->is_in_stock() && lmx_is_last_item($product->get_id())) echo '<span class="lmx-last-badge">Last item</span>';
});

/* ---------- [lmx_stock_note] – stock line under the price ---------- */
add_shortcode('lmx_stock_note', function () {
    global $product;
    if (!is_product() || !$product) return '';
    if (!$product->is_in_stock()) return '<p class="lmx-stock lmx-stock--out"><span class="lmx-stock__dot"></span>Sold out</p>';
    if (lmx_is_last_item($product->get_id()))
        return '<p class="lmx-stock lmx-stock--last"><span class="lmx-stock__dot"></span><strong>Last item in stock</strong><span class="lmx-stock__sep">·</span>It’s going fast</p>';
    return '<p class="lmx-stock lmx-stock--in"><span class="lmx-stock__dot"></span>In stock</p>';
});

/* ---------- [lmx_size_guide] – shoe size chart (old site: "Maattabel & pasvorm") ---------- */
function lmx_is_shoe_product($product_id) {
    static $shoe_ids = null;
    if ($shoe_ids === null) {
        $shoe_ids = [];
        foreach (['schoenen-heren', 'dames-schoenen'] as $slug) {
            $t = get_term_by('slug', $slug, 'product_cat');
            if ($t) $shoe_ids = array_merge($shoe_ids, [(int)$t->term_id], array_map('intval', get_term_children($t->term_id, 'product_cat')));
        }
    }
    return (bool)array_intersect($shoe_ids, wc_get_product_term_ids($product_id, 'product_cat'));
}
add_shortcode('lmx_size_guide', function () {
    global $product;
    if (!is_product() || !$product || !lmx_is_shoe_product($product->get_id())) return '';
    $rows = [[39, '24.5 cm', '6'], [40, '25.2 cm', '6.5'], [41, '25.9 cm', '7.5'], [42, '26.7 cm', '8.5'], [43, '27.3 cm', '9'], [44, '28.0 cm', '10'], [45, '28.7 cm', '11'], [46, '29.4 cm', '12']];
    $table = '<table class="lmx-sg-table"><thead><tr><th>EU</th><th>Foot length</th><th>UK</th></tr></thead><tbody>';
    foreach ($rows as $r) $table .= '<tr><td>' . $r[0] . '</td><td>' . $r[1] . '</td><td>' . $r[2] . '</td></tr>';
    $table .= '</tbody></table>';
    $wa = 'https://wa.me/31643584328';
    return '<button type="button" class="lmx-sg-btn" data-lmx-sg-open aria-haspopup="dialog">'
        . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 8.5h19v7h-19z"/><path d="M6.5 8.5v3M10.5 8.5v4M14.5 8.5v3M18.5 8.5v4"/></svg>'
        . '<span>Size guide &amp; fit</span></button>'
        . '<dialog class="lmx-sg" aria-labelledby="lmx-sg-title"><div class="lmx-sg__box">'
        . '<button type="button" class="lmx-sg__close" data-lmx-sg-close aria-label="Close"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>'
        . '<p class="lmx-sg__eyebrow">Size guide</p><h3 id="lmx-sg-title">Shoe sizes</h3>'
        . '<p class="lmx-sg__tip">These shoes fit <strong>true to size</strong>. Measure your foot from heel to longest toe and choose the matching size. In between two sizes? Take the larger one.</p>'
        . $table
        . '<p class="lmx-sg__help">Still not sure? <a href="' . esc_url($wa) . '">Send us a message</a> — we’re happy to help you find the right size.</p>'
        . '</div></dialog>';
});

/* ---------- Recently viewed (old site: "Recent Views") ---------- */
add_action('template_redirect', function () {
    if (!is_singular('product')) return;
    $ids = isset($_COOKIE['lmx_recent']) ? array_filter(array_map('absint', explode('|', wp_unslash($_COOKIE['lmx_recent'])))) : [];
    $id = get_queried_object_id();
    $ids = array_values(array_diff($ids, [$id]));
    array_unshift($ids, $id);
    $ids = array_slice($ids, 0, 12);
    wc_setcookie('lmx_recent', implode('|', $ids), time() + 30 * DAY_IN_SECONDS);
    $_COOKIE['lmx_recent'] = implode('|', $ids);
}, 20);
add_shortcode('lmx_recently_viewed', function () {
    $ids = isset($_COOKIE['lmx_recent']) ? array_filter(array_map('absint', explode('|', wp_unslash($_COOKIE['lmx_recent'])))) : [];
    if (is_singular('product')) $ids = array_values(array_diff($ids, [get_queried_object_id()]));
    $ids = array_slice($ids, 0, 8);
    if (!$ids) return '';
    $html = do_shortcode('[products ids="' . implode(',', $ids) . '" orderby="post__in" columns="4" limit="8"]');
    if (!$html) return '';
    return '<section class="lmx-recent"><div class="lmx-recent__head"><h2 class="lmx-recent__title">Recently viewed</h2></div><div class="btq-rail">' . $html . '</div></section>';
});
/* ---------- Gallery: previous / next arrows like the old site ---------- */
add_filter('woocommerce_single_product_carousel_options', function ($o) {
    $o['directionNav'] = true;
    $o['prevText'] = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg><span class="screen-reader-text">Previous</span>';
    $o['nextText'] = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg><span class="screen-reader-text">Next</span>';
    return $o;
});

/* ---------- Size guide dialog behaviour ---------- */
add_action('wp_footer', function () {
    if (!is_product()) return;
    ?>
<script>
(function () {
  var d = document.querySelector('dialog.lmx-sg'); if (!d) return;
  document.querySelectorAll('[data-lmx-sg-open]').forEach(function (b) { b.addEventListener('click', function () { if (d.showModal) d.showModal(); else d.setAttribute('open', ''); }); });
  d.querySelectorAll('[data-lmx-sg-close]').forEach(function (b) { b.addEventListener('click', function () { d.close ? d.close() : d.removeAttribute('open'); }); });
  d.addEventListener('click', function (e) { if (e.target === d) d.close(); });   // click on the backdrop closes it
})();
</script>
    <?php
}, 97);

/* ---------- Product meta: no duplicate category names (imported Dutch + homepage English categories) ---------- */
add_filter('term_links-product_cat', function ($links) {
    $seen = [];
    return array_values(array_filter($links, function ($l) use (&$seen) {
        $k = strtolower(trim(wp_strip_all_tags($l)));
        if (isset($seen[$k])) return false;
        return $seen[$k] = true;
    }));
});

/* ---------- English product descriptions (storefront only; Dutch originals stay in the DB/admin) ---------- */
function lmx_en_frontend() {
    if (is_admin() && !wp_doing_ajax()) return false;
    if (defined('REST_REQUEST') && REST_REQUEST && strpos($_SERVER['REQUEST_URI'] ?? '', '/wc/store') === false) return false;   // Store API (block cart/checkout) stays English
    if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode()) return false;
    return true;
}
add_filter('the_content', function ($c) {
    if (!lmx_en_frontend() || get_post_type() !== 'product') return $c;
    $en = get_post_meta(get_the_ID(), '_lmx_content_en', true);
    return $en !== '' ? $en : $c;
}, 1);
add_filter('woocommerce_short_description', function ($e) {
    global $post;
    if (!lmx_en_frontend() || !$post || $post->post_type !== 'product') return $e;
    $en = get_post_meta($post->ID, '_lmx_excerpt_en', true);
    return $en !== '' ? $en : $e;
}, 1);

/* ---------- English product titles (meta _lmx_title_en from build-scripts/12-translate-titles.php) ---------- */
function lmx_title_en_for($id) {
    $en = get_post_meta($id, '_lmx_title_en', true);
    return $en !== '' ? $en : null;
}
add_filter('the_title', function ($t, $id = 0) {
    if (!$id || !lmx_en_frontend() || get_post_type($id) !== 'product') return $t;
    return lmx_title_en_for($id) ?? $t;
}, 1, 2);
add_filter('single_post_title', function ($t, $post) {
    if (!$post || $post->post_type !== 'product' || !lmx_en_frontend()) return $t;
    return lmx_title_en_for($post->ID) ?? $t;
}, 1, 2);
add_filter('woocommerce_product_get_name', function ($n, $p) {
    return lmx_en_frontend() ? (lmx_title_en_for($p->get_id()) ?? $n) : $n;
}, 1, 2);
add_filter('woocommerce_product_variation_get_name', function ($n, $v) {
    if (!lmx_en_frontend() || !($en = lmx_title_en_for($v->get_parent_id()))) return $n;
    $nl = get_post_field('post_title', $v->get_parent_id());
    return strpos($n, $nl) === 0 ? $en . substr($n, strlen($nl)) : $n;
}, 1, 2);
// Search also matches the English titles/descriptions, so "black" or "jacket" find Dutch-titled products.
add_filter('posts_search', function ($sql, $q) {
    if (is_admin() || !$q->is_main_query() || !$q->is_search() || $sql === '') return $sql;
    global $wpdb;
    $terms = $q->get('search_terms');
    if (!$terms) return $sql;
    $likes = [];
    foreach ($terms as $t) $likes[] = $wpdb->prepare('meta_value LIKE %s', '%' . $wpdb->esc_like($t) . '%');
    $ids = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key IN ('_lmx_title_en','_lmx_content_en') AND " . implode(' AND ', $likes);
    return preg_replace('/^\s*AND\s*\(/', " AND ($wpdb->posts.ID IN ($ids) OR (", $sql, 1) . ')';
}, 10, 2);
// Image alt texts were imported as "<Dutch title> - Brand | LMX Boutique": swap in the English title.
function lmx_title_map() {
    static $map = null;
    if ($map !== null) return $map;
    global $wpdb;
    $map = [];
    foreach ($wpdb->get_results("SELECT p.post_title t, m.meta_value e FROM $wpdb->posts p JOIN $wpdb->postmeta m ON m.post_id=p.ID AND m.meta_key='_lmx_title_en'") as $r) {
        $map[html_entity_decode($r->t, ENT_QUOTES, 'UTF-8')] = $r->e;
    }
    return $map;
}
add_filter('wp_get_attachment_image_attributes', function ($a) {
    if (!empty($a['alt']) && lmx_en_frontend()) $a['alt'] = lmx_nl2en(strtr(html_entity_decode($a['alt'], ENT_QUOTES, 'UTF-8'), lmx_title_map()));
    return $a;
}, 20);

/**
 * Dutch → English for short product phrases (titles, image alts). Phrases first (word order changes),
 * then words; case-insensitive, keeps a leading capital. Used by build-scripts/12-translate-titles.php too.
 */
function lmx_nl2en($s) {
    static $re = null, $dict = null;
    if ($re === null) {
        $dict = [
            'donsjas met bontkraag en capuchon' => 'down jacket with fur collar and hood',
            'donsjas met capuchon' => 'hooded down jacket', 'puffer jas met capuchon' => 'hooded puffer jacket',
            'bomber linen jas' => 'linen bomber jacket', 'suede hoodie jack' => 'suede hooded jacket',
            'louis vuitton-sportschoenen' => 'Louis Vuitton Sports Shoes', 'ski brill' => 'ski goggles', 'skibrill' => 'ski goggles',
            'volledig zwart' => 'all black', 'blauw jean' => 'blue jean', 'messengerbag' => 'messenger bag',
            'donsjas' => 'down jacket', 'jas' => 'jacket', 'trui' => 'sweater', 'zwembroek' => 'swim shorts',
            'broek' => 'trousers', 'schoudertas' => 'shoulder bag', 'toilettas' => 'toiletry bag', 'handtas' => 'handbag', 'tas' => 'bag',
            'bril' => 'glasses', 'regenlaars' => 'rain boot', 'sandalen' => 'sandals', 'trainingspak' => 'tracksuit',
            'veterschoen' => 'lace-up shoe', 'horloge' => 'watch', 'wijzerplaat' => 'dial', 'bontkraag' => 'fur collar',
            'capuchon' => 'hood', 'kraag' => 'collar', 'opstaande' => 'stand-up', 'knoop' => 'button', 'rits' => 'zip',
            'taille' => 'waist', 'rug' => 'back', 'doos' => 'box', 'dustbag' => 'dust bag', 'maattabel' => 'size chart',
            'heren' => "men's", 'dames' => "women's", 'kleuren' => 'colours', 'alle' => 'all',
            'binnenkant' => 'inside', 'achterkant' => 'back', 'zijkant' => 'side', 'voorkant' => 'front',
            'zijaanzicht' => 'side view', 'vooraanzicht' => 'front view', 'detailfoto' => 'detail photo', 'hangend' => 'hanging',
            'lakleer' => 'patent leather', 'leer' => 'leather', 'leren' => 'leather', 'gevlochten' => 'woven',
            'gewatteerde' => 'padded', 'reflecterende' => 'reflective', 'reflecterend' => 'reflective', 'accenten' => 'accents',
            'h-zool' => 'H sole', 'cc-hak' => 'CC heel', 'zooldetail' => 'sole detail', 'zool' => 'sole', 'neus' => 'toe',
            'veters' => 'laces', 'stiksel' => 'stitching', 'suède' => 'suede', 'crème' => 'cream', 'creme' => 'cream', 'reliëf' => 'relief',
            'olijfgroen' => 'olive green', 'saliegroen' => 'sage green', 'grijsgroen' => 'grey-green', 'mintgroen' => 'mint green',
            'donkerblauw' => 'dark blue', 'lichtblauw' => 'light blue', 'ijsblauw' => 'ice blue', 'marineblauwe' => 'navy',
            'lavendelblauwe' => 'lavender-blue', 'oker-geel' => 'ochre yellow', 'zandkleurige' => 'sand-coloured',
            'zilverkleurige' => 'silver-tone', 'lichtgrijs' => 'light grey', 'antraciet' => 'anthracite', 'bordeaux' => 'burgundy',
            'zwarte' => 'black', 'witte' => 'white', 'grijze' => 'grey', 'groene' => 'green', 'gele' => 'yellow', 'blauwe' => 'blue',
            'gouden' => 'gold', 'zilveren' => 'silver', 'zilver' => 'silver',
            'zwart' => 'black', 'wit' => 'white', 'grijs' => 'grey', 'bruin' => 'brown', 'blauw' => 'blue', 'groen' => 'green',
            'rood' => 'red', 'roze' => 'pink', 'geel' => 'yellow', 'oranje' => 'orange', 'oker' => 'ochre', 'karamel' => 'caramel',
            'zand' => 'sand', 'mat' => 'matte', 'volledig' => 'all', 'met' => 'with', 'en' => 'and', 'van' => 'of', 'op' => 'on',
            'de' => 'the', 'het' => 'the',
        ];
        uksort($dict, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
        $re = '/(?<![\p{L}\-])(' . implode('|', array_map(fn($k) => preg_quote($k, '/'), array_keys($dict))) . ')(?![\p{L}])/iu';
    }
    $out = preg_replace_callback($re, function ($m) use ($dict) {
        $en = $dict[mb_strtolower($m[1])];
        $first = mb_substr($m[1], 0, 1);
        if ($first === mb_strtolower($first) || $en !== mb_strtolower($en)) return $en;
        $t = ucwords($en, " -");
        return preg_replace_callback('/(?<=\s)(With|And|Of|The|On)(?=\s)/', fn($w) => strtolower($w[1]), $t);   // Title Case, small words lower
    }, $s);
    return preg_replace('/\s{2,}/', ' ', $out);
}
function lmx_alt_en($s) { return lmx_nl2en(strtr(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'), lmx_title_map())); }
// Gallery thumbnail nav (data-thumb-alt) and variation image data carry the raw alt/title too.
add_filter('woocommerce_single_product_image_thumbnail_html', function ($html) {
    if (!lmx_en_frontend()) return $html;
    return preg_replace_callback('/data-thumb-alt="([^"]*)"/', fn($m) => 'data-thumb-alt="' . esc_attr(lmx_alt_en($m[1])) . '"', $html);
}, 20);
add_filter('woocommerce_available_variation', function ($d) {
    if (lmx_en_frontend() && !empty($d['image'])) foreach (['alt', 'title', 'caption'] as $k) if (!empty($d['image'][$k])) $d['image'][$k] = lmx_alt_en($d['image'][$k]);
    return $d;
}, 20);
// Raw getters (structured data, Store API, JSON in the page) get the English text as well.
add_filter('woocommerce_product_get_description', function ($v, $p) {
    if (!lmx_en_frontend()) return $v;
    $en = get_post_meta($p->get_id(), '_lmx_content_en', true);
    return $en !== '' ? $en : $v;
}, 1, 2);
add_filter('woocommerce_product_get_short_description', function ($v, $p) {
    if (!lmx_en_frontend()) return $v;
    $en = get_post_meta($p->get_id(), '_lmx_excerpt_en', true);
    return $en !== '' ? $en : $v;
}, 1, 2);
// Elementor prints $post->post_excerpt raw into elementorFrontendConfig: swap it on the in-memory post (never saved).
add_action('wp', function () {
    if (!is_singular('product') || !lmx_en_frontend()) return;
    $p = get_queried_object();
    if ($p instanceof WP_Post && ($en = get_post_meta($p->ID, '_lmx_excerpt_en', true)) !== '') $p->post_excerpt = $en;
});
