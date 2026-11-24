<?php
/**
 * Exports the unique Dutch text blocks of all product descriptions (long + short) for translation.
 * A "block" is the inner HTML of p / li / h1-h6 / td / th / figcaption, or a loose line of text.
 * Writes translations/nl-XX.json chunks: { "<id>": "<inner html>" }. The id is md5 of the Dutch block.
 */
require_once __DIR__ . '/lib.php';
global $wpdb;
function lmx_blocks($html) {
    $out = [];
    if (preg_match_all('#<(p|li|h[1-6]|td|th|figcaption|dt|dd)(\s[^>]*)?>(.*?)</\1>#is', $html, $m)) foreach ($m[3] as $b) $out[] = trim($b);
    // loose text outside block tags (e.g. plain short descriptions)
    $loose = preg_replace('#<(p|li|h[1-6]|td|th|figcaption|dt|dd|table|thead|tbody|tr|ul|ol)(\s[^>]*)?>.*?</\1>#is', "\n", $html);
    foreach (preg_split('/\n+/', $loose) as $l) { $l = trim($l); if ($l !== '') $out[] = $l; }
    return $out;
}
function lmx_needs_translation($b) {
    $t = trim(html_entity_decode(wp_strip_all_tags($b), ENT_QUOTES, 'UTF-8'));
    if ($t === '' || mb_strlen($t) < 2) return false;
    if (!preg_match('/\p{L}{2,}/u', $t)) return false;                        // numbers / sizes only
    if (preg_match('/^(EU|US|UK|CM|IT|FR|XS|S|M|L|XL|XXL|XXXL|One size)$/i', $t)) return false;
    return true;
}
$rows = $wpdb->get_results("SELECT ID, post_content, post_excerpt FROM $wpdb->posts WHERE post_type='product' AND post_status='publish'");
$blocks = [];
foreach ($rows as $r) foreach (array_merge(lmx_blocks($r->post_content), lmx_blocks($r->post_excerpt)) as $b) if (lmx_needs_translation($b)) $blocks[md5($b)] = $b;
ksort($blocks);
$chunks = array_chunk($blocks, 130, true);
foreach (glob(__DIR__ . '/translations/nl-*.json') as $old) unlink($old);
foreach ($chunks as $i => $c) file_put_contents(sprintf('%s/translations/nl-%02d.json', __DIR__, $i + 1), json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
printf("%d unique blocks (%d chars) in %d chunks\n", count($blocks), array_sum(array_map('mb_strlen', $blocks)), count($chunks));
