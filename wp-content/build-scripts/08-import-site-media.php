<?php
/**
 * Imports the old site's own design media (downloaded to C:/xampp/boutique-migration/site-media with the user's OK)
 * into the Media Library and remembers the IDs in the build state:
 *   cat_banner_{shoes|t-shirts|jackets|pants|bags|accessories}, trending_videos[], shop_banner_{heren|dames|sale|default}_{video|poster}
 * Safe to re-run: files already imported are reused.
 */
require_once __DIR__ . '/lib.php';
const SITE_MEDIA = 'C:/xampp/boutique-migration/site-media';

function lmx_import_media($filename, $title) {
    $existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_lmx_site_media', 'meta_value' => $filename]);
    if ($existing) return (int)$existing[0];
    $src = SITE_MEDIA . '/' . $filename;
    if (!file_exists($src)) { echo "  missing $filename\n"; return 0; }
    $up = wp_upload_dir();
    $dir = $up['basedir'] . '/lmx-site-media';
    wp_mkdir_p($dir);
    $dest = $dir . '/' . $filename;
    copy($src, $dest);
    $type = wp_check_filetype($filename)['type'];
    $id = wp_insert_attachment(['post_mime_type' => $type, 'post_title' => $title, 'post_status' => 'inherit'], $dest);
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $dest));
    update_post_meta($id, '_lmx_site_media', $filename);
    if (str_starts_with($type, 'image/')) update_post_meta($id, '_wp_attachment_image_alt', $title);
    return (int)$id;
}

$cats = [
    'shoes' => ['banner-schoenen-sneakers-1.jpg', 'Shoes'], 't-shirts' => ['banner-tshirts-dior-tears.jpg', 'T-Shirts'],
    'jackets' => ['banner-jassen-lv-jacket.jpg', 'Jackets'], 'pants' => ['banner-broeken-loewe-1.jpg', 'Pants'],
    'bags' => ['tassen-banner-hermes.jpg', 'Bags'], 'accessories' => ['banner-accessoires-gwagon-hq.jpg', 'Accessories'],
];
foreach ($cats as $slug => [$file, $title]) bstate("cat_banner_$slug", lmx_import_media($file, "Category banner – $title"));

$videos = [];
for ($i = 1; $i <= 7; $i++) $videos[] = lmx_import_media("trending-outfit-$i.mp4", "Trending outfit $i");
bstate('trending_videos', array_values(array_filter($videos)));

$shop = [
    'heren'   => ['heren-banner-video-compressed.mp4', 'heren-banner-gucci-luxury.jpg'],
    'dames'   => ['dames-banner-video-compressed.mp4', 'dames-banner-luxury-boutique.jpg'],
    'sale'    => [null, 'sale-banner-luxury.jpg'],
    'default' => [null, 'sfdsfd.jpg'],
];
foreach ($shop as $key => [$video, $poster]) {
    bstate("shop_banner_{$key}_video", $video ? lmx_import_media($video, "Shop banner video – $key") : 0);
    bstate("shop_banner_{$key}_poster", lmx_import_media($poster, "Shop banner – $key"));
}
echo "Imported: 6 category banners, " . count(array_filter($videos)) . " trending videos, shop banners for " . implode(', ', array_keys($shop)) . "\n";
