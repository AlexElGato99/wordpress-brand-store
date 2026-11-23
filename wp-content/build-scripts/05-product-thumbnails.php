<?php
/**
 * Product grid images: same 2:3 portrait crop as the old site (500x750), newest products first.
 * Regenerates only the "woocommerce_thumbnail" size of each product's main image; originals are untouched.
 * Safe to re-run (skips images that already have a 500x750 thumbnail).
 */
require_once __DIR__ . '/lib.php';
ini_set('memory_limit', '1024M');
set_time_limit(0);

update_option('woocommerce_thumbnail_cropping', 'custom');
update_option('woocommerce_thumbnail_cropping_custom_width', '2');
update_option('woocommerce_thumbnail_cropping_custom_height', '3');
update_option('woocommerce_thumbnail_image_width', '500');
update_option('woocommerce_default_catalog_orderby', 'date');   // newest first, like the old site
$W = 500; $H = 750;

$ids = get_posts(['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private'], 'numberposts' => -1, 'fields' => 'ids']);
$done = $skipped = $failed = 0;
foreach ($ids as $pid) {
    $aid = (int)get_post_thumbnail_id($pid);
    if (!$aid) continue;
    $meta = wp_get_attachment_metadata($aid);
    $file = get_attached_file($aid);
    if (!$meta || !$file || !file_exists($file)) { $failed++; continue; }
    $cur = $meta['sizes']['woocommerce_thumbnail'] ?? null;
    if ($cur && abs($cur["width"] / max(1, $cur["height"]) - $W / $H) < 0.01 && $cur["file"] !== basename($file) && file_exists(dirname($file) . '/' . $cur['file'])) { $skipped++; continue; }
    $ed = wp_get_image_editor($file);
    if (is_wp_error($ed)) { $failed++; continue; }
    $ed->set_quality(82);
    $r = $ed->resize($W, $H, true);                       // centre crop to 2:3
    if (is_wp_error($r)) {
        // source smaller than 500x750: centre-crop to 2:3 at its own size (no upscaling)
        $sz = $ed->get_size();
        $cw = min($sz['width'], (int)floor($sz['height'] * $W / $H)); $ch = (int)floor($cw * $H / $W);
        $r = $ed->crop((int)(($sz['width'] - $cw) / 2), (int)(($sz['height'] - $ch) / 2), $cw, $ch);
        if (is_wp_error($r)) { $failed++; continue; }
    }
    $saved = $ed->save($ed->generate_filename("{$W}x{$H}"));
    if (is_wp_error($saved)) { $failed++; continue; }
    // remove the previous grid file if it is not used by another size
    if ($cur && $cur['file'] !== $saved['file']) {
        $still_used = false;
        foreach ($meta['sizes'] as $k => $s) if ($k !== 'woocommerce_thumbnail' && $s['file'] === $cur['file']) $still_used = true;
        if (!$still_used) @unlink(dirname($file) . '/' . $cur['file']);
    }
    $meta['sizes']['woocommerce_thumbnail'] = ['file' => $saved['file'], 'width' => $saved['width'], 'height' => $saved['height'], 'mime-type' => $saved['mime-type'], 'filesize' => filesize($saved['path'])];
    wp_update_attachment_metadata($aid, $meta);
    if (++$done % 50 === 0) echo "  $done regenerated\n";
}
wc_delete_product_transients();
\Elementor\Plugin::$instance->files_manager->clear_cache();
echo "Grid images: $done regenerated, $skipped already 2:3, $failed failed\n";
