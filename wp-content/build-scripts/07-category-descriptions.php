<?php
/**
 * Stores English category descriptions (category-descriptions-en.json) in term meta "lmx_description_en".
 * The storefront shows them via the get_product_cat filter in mu-plugins/lmx-tweaks.php;
 * the original Dutch description stays untouched in the admin. Safe to re-run after editing the JSON.
 */
require_once __DIR__ . '/lib.php';
$en = json_decode(file_get_contents(__DIR__ . '/category-descriptions-en.json'), true, 512, JSON_THROW_ON_ERROR);
$n = 0;
foreach ($en as $term_id => $text) {
    if (!get_term((int)$term_id, 'product_cat')) { echo "  skip $term_id (not found)\n"; continue; }
    update_term_meta((int)$term_id, 'lmx_description_en', wp_kses_post($text));
    $n++;
}
echo "English descriptions stored: $n\n";
