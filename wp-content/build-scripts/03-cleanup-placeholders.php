<?php
/**
 * Removes the sample products and all generated placeholder images, and strips their
 * references from Elementor pages/templates so you can add real products & photos.
 * Keeps: categories, Size attribute, coupons, menus, pages and templates.
 */
require_once __DIR__ . '/lib.php';

echo "== Deleting sample products\n";
$pids = bstate('products') ?: [];
foreach ($pids as $slug => $id) {
    $p = wc_get_product($id);
    if (!$p) continue;
    foreach ($p->get_children() as $vid) wp_delete_post($vid, true);
    wp_delete_post($id, true);
    echo "  - deleted #$id $slug\n";
}
bstate('products', []);

echo "== Deleting placeholder images\n";
$state = get_option('boutique_build_state', []);
$deleted = [];
foreach ($state as $k => $v) {
    if (strpos($k, 'img_') !== 0) continue;
    if ($v && get_post($v)) { $deleted[(int)$v] = wp_get_attachment_url($v); wp_delete_attachment($v, true); }
    unset($state[$k]);
}
// catch any leftover generated files registered as attachments
foreach (get_posts(['post_type' => 'attachment', 'numberposts' => -1, 'post_status' => 'any']) as $a) {
    if (strpos(basename(get_attached_file($a->ID)), 'boutique-') === 0) { $deleted[$a->ID] = wp_get_attachment_url($a->ID); wp_delete_attachment($a->ID, true); }
}
update_option('boutique_build_state', $state, false);
echo "  deleted " . count($deleted) . " images\n";

echo "== Removing category thumbnails\n";
foreach (bstate('cats') ?: [] as $slug => $tid) delete_term_meta($tid, 'thumbnail_id');

echo "== Stripping image references from Elementor data\n";
$ids = array_map('intval', array_keys($deleted));
$is_deleted = function ($img) use ($ids) {
    return is_array($img) && ((isset($img['id']) && in_array((int)$img['id'], $ids, true)) || (!empty($img['url']) && strpos($img['url'], '/boutique-') !== false));
};
$walk = function (array $els) use (&$walk, $is_deleted) {
    foreach ($els as &$el) {
        $s = &$el['settings'];
        foreach (['background_image', 'background_image_tablet', 'background_image_mobile'] as $k) {
            if (isset($s[$k]) && $is_deleted($s[$k])) {
                $s[$k] = ['url' => '', 'id' => ''];
                // keep a solid fallback so text stays readable until you add a photo
                if (($s['background_background'] ?? '') === 'classic' && empty($s['background_color'])) {
                    $s['background_color'] = (strpos($s['css_classes'] ?? '', 'btq-tile') !== false) ? SOFT : '#14110D';
                }
            }
        }
        if (isset($s['image']) && $is_deleted($s['image'])) $s['image'] = ['url' => \Elementor\Utils::get_placeholder_image_src(), 'id' => ''];
        if (isset($s['gallery']) && is_array($s['gallery'])) $s['gallery'] = array_values(array_filter($s['gallery'], fn($g) => !$is_deleted($g)));
        if (!empty($el['elements'])) $el['elements'] = $walk($el['elements']);
        unset($s);
    }
    return $els;
};
$posts = get_posts(['post_type' => ['page', 'elementor_library'], 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_elementor_data']);
foreach ($posts as $p) {
    $raw = get_post_meta($p->ID, '_elementor_data', true);
    if (!$raw || strpos($raw, 'boutique-') === false) continue;
    $data = json_decode($raw, true);
    if (!is_array($data)) continue;
    el_save_post($p->ID, $walk($data), (array)get_post_meta($p->ID, '_elementor_page_settings', true));
    echo "  - cleaned {$p->post_title} (#{$p->ID})\n";
}

wc_delete_product_transients();
delete_transient('wc_products_onsale');
\Elementor\Plugin::$instance->files_manager->clear_cache();
echo "DONE\n";
