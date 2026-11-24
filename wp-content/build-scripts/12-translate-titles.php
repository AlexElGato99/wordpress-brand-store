<?php
/**
 * English product titles for the storefront. Dutch post_title stays in the DB/admin;
 * the English title goes to meta _lmx_title_en (read by mu-plugins/lmx-pdp.php).
 * Titles are formulaic ("Model – Colour met Detail"), so the curated phrase/word list in
 * lmx_nl2en() (mu-plugins/lmx-pdp.php, shared with the image-alt filter) is used.
 * Args: (none) apply  |  dry  = only print changes  |  clear = remove the meta.
 */
require_once __DIR__ . '/lib.php';
global $wpdb;
$mode = $argv[1] ?? 'apply';
if ($mode === 'clear') { echo $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key='_lmx_title_en'") . " rows removed\n"; exit; }
if (!function_exists('lmx_nl2en')) { fwrite(STDERR, "lmx_nl2en() missing: is mu-plugins/lmx-pdp.php loaded?\n"); exit(1); }

$rows = $wpdb->get_results("SELECT ID, post_title FROM $wpdb->posts WHERE post_type='product' AND post_status='publish'");
$changed = 0; $seen = [];
foreach ($rows as $r) {
    $nl = html_entity_decode($r->post_title, ENT_QUOTES, 'UTF-8');
    $en = lmx_nl2en($nl);
    if ($en === $nl) { if ($mode !== 'dry') delete_post_meta($r->ID, '_lmx_title_en'); continue; }
    $changed++;
    if ($mode === 'dry') { if (!isset($seen[$nl])) echo "$nl  =>  $en\n"; $seen[$nl] = 1; continue; }
    update_post_meta($r->ID, '_lmx_title_en', wp_slash($en));
}
if ($mode !== 'dry') $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key='_elementor_element_cache'");
echo "$changed of " . count($rows) . " titles translated" . ($mode === 'dry' ? ' (dry run)' : '') . "\n";
