<?php
/**
 * Applies translations/en-*.json (md5(Dutch block) => English inner HTML) to every published product.
 * The Dutch post_content / post_excerpt stay untouched; English goes to meta _lmx_content_en / _lmx_excerpt_en,
 * which mu-plugins/lmx-pdp.php shows on the storefront. Safe to re-run (e.g. after editing a translation).
 * Run "php 11-apply-product-translations.php clear" to delete the English meta again.
 */
require_once __DIR__ . '/lib.php';
global $wpdb;

if (($argv[1] ?? '') === 'clear') {
    $n = $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key IN ('_lmx_content_en','_lmx_excerpt_en')");
    echo "Removed $n meta rows\n";
    exit;
}

$tr = [];
foreach (glob(__DIR__ . '/translations/en-*.json') as $f) {
    $j = json_decode(file_get_contents($f), true);
    if (!is_array($j)) { fwrite(STDERR, "Invalid JSON: $f\n"); exit(1); }
    $tr += $j;
}

function lmx_tr_html($html, $tr, &$hit, &$miss) {
    if (trim($html) === '') return $html;
    $block = '#<(p|li|h[1-6]|td|th|figcaption|dt|dd)(\s[^>]*)?>(.*?)</\1>#is';
    // 1) block elements: swap the inner HTML
    $out = preg_replace_callback($block, function ($m) use ($tr, &$hit, &$miss) {
        $k = md5(trim($m[3]));
        if (isset($tr[$k])) { $hit++; return '<' . $m[1] . $m[2] . '>' . $tr[$k] . '</' . $m[1] . '>'; }
        if (preg_match('/\p{L}{3,}/u', wp_strip_all_tags($m[3]))) $miss[$k] = trim($m[3]);
        return $m[0];
    }, $html);
    // 2) loose lines outside block containers (taken from the original HTML, as the exporter did)
    $loose = preg_replace('#<(p|li|h[1-6]|td|th|figcaption|dt|dd|table|thead|tbody|tr|ul|ol)(\s[^>]*)?>.*?</\1>#is', "\n", $html);
    foreach (preg_split('/\n+/', $loose) as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $k = md5($l);
        if (isset($tr[$k])) { $out = str_replace($l, $tr[$k], $out); $hit++; }
        elseif (preg_match('/\p{L}{3,}/u', wp_strip_all_tags($l)) && stripos($l, 'wp-video') === false) $miss[$k] = $l;
    }
    return $out;
}

$rows = $wpdb->get_results("SELECT ID, post_content, post_excerpt FROM $wpdb->posts WHERE post_type='product' AND post_status='publish'");
$hit = 0; $miss = []; $n = 0;
foreach ($rows as $r) {
    $c = lmx_tr_html($r->post_content, $tr, $hit, $miss);
    $e = lmx_tr_html($r->post_excerpt, $tr, $hit, $miss);
    if ($c !== $r->post_content) update_post_meta($r->ID, '_lmx_content_en', wp_slash($c)); else delete_post_meta($r->ID, '_lmx_content_en');
    if ($e !== $r->post_excerpt) update_post_meta($r->ID, '_lmx_excerpt_en', wp_slash($e)); else delete_post_meta($r->ID, '_lmx_excerpt_en');
    $n++;
}
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key='_elementor_element_cache'");
printf("%d products, %d translations loaded, %d blocks replaced, %d untranslated blocks\n", $n, count($tr), $hit, count($miss));
foreach (array_slice($miss, 0, 15, true) as $k => $v) echo "  MISSING $k: " . mb_substr(wp_strip_all_tags($v), 0, 90) . "\n";
