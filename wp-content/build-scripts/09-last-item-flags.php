<?php
/**
 * Marks products that the old site showed as "LAATSTE STUK" (last item): low_stock_remaining == 1 in the catalog snapshot.
 * Sets post meta _lmx_last_item = 1 (display only – stock management is not changed). Safe to re-run.
 */
require_once __DIR__ . '/lib.php';
global $wpdb;
$snapshot = json_decode(file_get_contents('C:/xampp/boutique-migration/products.json'), true, 512, JSON_THROW_ON_ERROR);
$map = $wpdb->get_results("SELECT meta_value AS src, post_id FROM $wpdb->postmeta WHERE meta_key = '_lmx_source_id'", OBJECT_K);
$set = $cleared = $unmatched = 0;
foreach ($snapshot as $p) {
    $post_id = isset($map[(string)$p['id']]) ? (int)$map[(string)$p['id']]->post_id : 0;
    if (!$post_id) { $unmatched++; continue; }
    if ((int)($p['low_stock_remaining'] ?? 0) === 1) { update_post_meta($post_id, '_lmx_last_item', 1); $set++; }
    elseif (delete_post_meta($post_id, '_lmx_last_item')) $cleared++;
}
wc_delete_product_transients();
echo "Last-item flags set: $set, cleared: $cleared, not matched: $unmatched\n";
