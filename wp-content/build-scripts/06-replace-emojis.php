<?php
/**
 * Replaces emojis in product descriptions / short descriptions with line icons (.lmx-ic-* classes from kit.css).
 * - "⚠️ Let op: …" paragraphs become a styled note with a warning icon.
 * - Feature list items that start with an emoji get one consistent check icon.
 * - Any other emoji is removed.
 * The original text of every changed product is saved once in post meta "_lmx_emoji_backup" (restore with: php 06-replace-emojis.php restore).
 */
require_once __DIR__ . '/lib.php';
global $wpdb;

$EMOJI = '[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2300}-\x{23FF}]\x{FE0F}?';
$ALERT = '<span class="lmx-ic lmx-ic-alert" aria-hidden="true"></span>';
$CHECK = '<span class="lmx-ic lmx-ic-check" aria-hidden="true"></span>';

if (($argv[1] ?? '') === 'restore') {
    foreach ($wpdb->get_col("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_lmx_emoji_backup'") as $pid) {
        $b = get_post_meta($pid, '_lmx_emoji_backup', true);
        $wpdb->update($wpdb->posts, ['post_content' => $b['content'], 'post_excerpt' => $b['excerpt']], ['ID' => $pid]);
        clean_post_cache($pid);
        delete_post_meta($pid, '_lmx_emoji_backup');
    }
    echo "restored\n"; exit;
}

$clean = function ($html) use ($EMOJI, $ALERT, $CHECK) {
    // Warning paragraphs
    $html = preg_replace("/<p>(\s*(?:<strong>)?)\s*\x{26A0}\x{FE0F}?\s*(.*?)<\/p>/su", '<p class="lmx-note">' . $ALERT . '<span>$1$2</span></p>', $html);
    // Feature bullets
    $html = preg_replace("/<li>\s*$EMOJI\s*/u", '<li class="lmx-feature">' . $CHECK, $html);
    // Emoji at the start of a line/paragraph
    $html = preg_replace("/(^|<p>|<br\s*\/?>|\n)\s*$EMOJI\s*/u", '$1' . $CHECK, $html);
    // Anything left over
    $html = preg_replace("/\s?$EMOJI/u", '', $html);
    return $html;
};

$rows = $wpdb->get_results("SELECT ID, post_content, post_excerpt FROM $wpdb->posts WHERE post_type IN ('product','product_variation') AND post_status <> 'trash'");
$changed = 0;
foreach ($rows as $r) {
    $c = $clean($r->post_content);
    $e = $clean($r->post_excerpt);
    if ($c === $r->post_content && $e === $r->post_excerpt) continue;
    if (!get_post_meta($r->ID, '_lmx_emoji_backup', true)) add_post_meta($r->ID, '_lmx_emoji_backup', ['content' => $r->post_content, 'excerpt' => $r->post_excerpt], true);
    // direct update: keeps the HTML exactly as written (no kses filtering) and creates no revisions
    $wpdb->update($wpdb->posts, ['post_content' => $c, 'post_excerpt' => $e], ['ID' => $r->ID]);
    clean_post_cache($r->ID);
    $changed++;
}
wc_delete_product_transients();
$left = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type IN ('product','product_variation') AND post_status <> 'trash' AND (post_content REGEXP %s OR post_excerpt REGEXP %s)", '[⚠✅]', '[⚠✅]'));
echo "Products cleaned: $changed (backups saved). Warning/check emojis left: $left\n";
