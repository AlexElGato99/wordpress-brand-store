<?php
/**
 * Styling / branding / performance pass for LMX Boutique.
 * Safe to re-run: it only updates settings, the global kit CSS, the main menu and the 404 template.
 * It does NOT create or delete products or images.
 */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/icons.php';

echo "== Brand\n";
update_option('blogname', 'LMX Boutique');
update_option('blogdescription', 'Premium Fashion & Luxury Essentials');

echo "== Elementor performance settings\n";
foreach ([
    'elementor_local_google_fonts' => '1',          // serve Jost from this site instead of Google
    'elementor_font_display' => 'swap',              // text visible while the font loads
    'elementor_optimized_image_loading' => '1',      // fetchpriority/lazy-loading on images
    'elementor_lazy_load_background_images' => '1',  // background images load when near the viewport
    'elementor_optimized_gutenberg_loading' => '1',  // no block-editor CSS on Elementor pages
    'elementor_css_print_method' => 'external',      // cacheable CSS files
    'elementor_experiment-e_optimized_css_files' => 'active',
    'elementor_load_fa4_shim' => '',
] as $k => $v) update_option($k, $v);

echo "== Astra: let Elementor load the font (avoids loading Jost twice), 24 products per page\n";
$astra = get_option('astra-settings', []);
$astra['body-font-family'] = 'inherit';
$astra['headings-font-family'] = 'inherit';
$astra['body-font-variant'] = '';
$astra['headings-font-variant'] = '';
$astra['shop-no-of-products'] = 24;
$astra['load-google-fonts-locally'] = false;
update_option('astra-settings', $astra);

echo "== WooCommerce\n";
update_option('woocommerce_enable_ajax_add_to_cart', 'yes');

echo "== Global kit (brand + CSS)\n";
$kit = \Elementor\Plugin::$instance->documents->get((int)get_option('elementor_active_kit'), false);
$kit->save(['settings' => array_merge($kit->get_settings() ?: [], [
    'site_name' => 'LMX Boutique',
    'global_image_lightbox' => '', // no lightbox script on every page
    'site_description' => 'Premium Fashion & Luxury Essentials',
    'custom_css' => file_get_contents(__DIR__ . '/kit.css') . "\n/* Brand SVG artwork (generated from icons.php) */\n" . lmx_artwork_css(),
])]);

echo "== Main menu (same structure as the old site, English labels)\n";
$L = ['Heren' => 'Men', 'Dames' => 'Women', 'Tassen' => 'Bags', 'Schoenen' => 'Shoes', 'Shirts' => 'Shirts', 'Polo’s' => 'Polos', 'Zomersetjes' => 'Summer Sets',
      'Truien' => 'Sweaters', 'Overhemden' => 'Shirts & Overshirts', 'Vesten' => 'Cardigans', 'Broeken' => 'Pants', 'Zwembroeken' => 'Swim Shorts', 'Jassen' => 'Jackets',
      'Tussenjassen' => 'Light Jackets', 'Winterjassen' => 'Winter Jackets', 'Trainingspakken' => 'Tracksuits', 'Accessoires' => 'Accessories', 'Horloges' => 'Watches',
      'Parfum' => 'Perfume', 'Petten & Mutsen' => 'Caps & Beanies', 'Zonnebrillen' => 'Sunglasses', 'Sandalen' => 'Sandals', 'Mutsen & Sjaals' => 'Beanies & Scarves',
      'Riemen' => 'Belts', 'Sieraden' => 'Jewellery', 'Voorraad' => 'In Stock', 'Op bestelling' => 'Made to Order', 'Slippers' => 'Slides'];
$menu_tree = [
    ['Heren', 'heren', [
        ['Tassen', 'heren-tassen', [['Louis Vuitton', 'heren-tassen-louisvuitton'], ['Dior', 'heren-tassen-dior'], ['Hermes', 'heren-tassen-hermes'], ['Gucci', 'heren-tassen-gucci'], ['Prada', 'heren-tassen-prada']]],
        ['Schoenen', 'schoenen-heren', [['Sneakers', 'sneakers'], ['Loafers', 'loafers'], ['Slippers', 'slippers'], ['Boots', 'heren-schoenen-boots']]],
        ['Shirts', 'shirts', [['Polo’s', 'polos'], ['T-Shirts', 't-shirts'], ['Zomersetjes', 'zomersetjes']]],
        ['Truien', 'truien', [['Hoodies', 'hoodies'], ['Overhemden', 'overhemden'], ['Sweaters', 'sweaters'], ['Vesten', 'vesten']]],
        ['Broeken', 'broeken', [['Cargo Pants', 'cargo-pants'], ['Jeans', 'jeans'], ['Shorts', 'shorts'], ['Zwembroeken', 'zwembroeken']]],
        ['Jassen', 'jassen', [['Bodywarmers', 'bodywarmers'], ['Tussenjassen', 'tussenjassen'], ['Winterjassen', 'winterjassen']]],
        ['Trainingspakken', 'trainingspakken', []],
        ['Accessoires', 'accessoires', [['Horloges', 'horloges'], ['Parfum', 'parfum'], ['Petten & Mutsen', 'petten-mutsen'], ['Zonnebrillen', 'zonnebrillen']]],
    ]],
    ['Dames', 'dames', [
        ['Tassen', 'dames-tassen', [['Louis Vuitton', 'dames-tassen-louisvuitton'], ['Dior', 'dames-tassen-dior'], ['Hermes', 'dames-tassen-hermes'], ['Gucci', 'dames-tassen-gucci'], ['Prada', 'dames-tassen-prada']]],
        ['Schoenen', 'dames-schoenen', [['Sneakers', 'dames-schoenen-sneakers'], ['Boots', 'dames-schoenen-boots'], ['Sandalen', 'dames-schoenen-sandalen']]],
        ['Jassen', 'dames-jassen', []],
        ['Accessoires', 'dames-accessoires', [['Mutsen & Sjaals', 'dames-mutsen'], ['Parfum', 'dames-parfum'], ['Riemen', 'dames-riemen'], ['Sieraden', 'dames-sieraden'], ['Zonnebrillen', 'dames-zonnebrillen']]],
    ]],
    ['Sale', 'sale', []],
    ['Voorraad', 'voorraad', []],
    ['Op bestelling', 'op-bestelling', []],
];
$menu = wp_get_nav_menu_object('Main Menu');
$mid = $menu ? $menu->term_id : wp_create_nav_menu('Main Menu');
foreach (wp_get_nav_menu_items($mid) ?: [] as $it) wp_delete_post($it->ID, true);
$missing = [];
$add = function ($items, $parent = 0) use (&$add, $mid, $L, &$missing) {
    foreach ($items as $it) {
        [$label, $slug] = $it; $children = $it[2] ?? [];
        $term = get_term_by('slug', $slug, 'product_cat');
        if (!$term) { $missing[] = $slug; continue; }
        $iid = wp_update_nav_menu_item($mid, 0, ['menu-item-title' => $L[$label] ?? $label, 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent,
            'menu-item-type' => 'taxonomy', 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $term->term_id]);
        if ($children) $add($children, $iid);
    }
};
$add($menu_tree);
foreach ([['About Us', bstate('page_about')], ['Contact', bstate('page_contact')]] as [$label, $pid]) {
    wp_update_nav_menu_item($mid, 0, ['menu-item-title' => $label, 'menu-item-status' => 'publish', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $pid]);
}
$loc = get_theme_mod('nav_menu_locations', []); $loc['primary'] = $mid; set_theme_mod('nav_menu_locations', $loc);
bstate('menu_main', get_term($mid)->slug);
echo '  items: ' . count(wp_get_nav_menu_items($mid)) . ($missing ? '  (categories not found yet: ' . implode(', ', $missing) . ')' : '') . "\n";

echo "== 404 page (Theme Builder)\n";
$shop_url = get_permalink(wc_get_page_id('shop'));
el_template('error-404', 'error-404', 'LMX 404 Page', [
    con(['content_width' => 'boxed', 'boxed_width' => sz(760), 'flex_direction' => 'column', 'flex_align_items' => 'center', 'flex_gap' => gap(18),
         'min_height' => sz(62, 'vh'), 'flex_justify_content' => 'center', 'padding' => box(90, 20, 90, 20), '_css_classes' => 'btq-pattern-dark',
         'background_background' => 'gradient', 'background_color' => '#1C1712', 'background_color_b' => '#050505', 'background_gradient_type' => 'radial'], [
        w('heading', array_merge(['title' => '404', 'header_size' => 'p', 'align' => 'center', '_css_classes' => 'btq-gold-text'], typo('typography', 110, '800', -2, null, 1, 80))),
        w('heading', array_merge(['title' => 'This page could not be found', 'header_size' => 'h1', 'title_color' => '#FFFFFF', 'align' => 'center'], typo('typography', 32, '700', 1, 'uppercase', 1.2, 24))),
        w('text-editor', array_merge(['editor' => '<p>The page may have moved or no longer exists. Search our collection or go back to the shop.</p>', 'text_color' => '#BDB6AA', 'align' => 'center'], typo('typography', 16, '400'))),
        w('search-form', ['skin' => 'classic', 'placeholder' => 'Search products…', 'button_type' => 'icon', 'size' => 50, '_element_width' => 'initial', '_element_custom_width' => sz(100, '%'),
            'input_background_color' => '#FFFFFF', 'input_text_color' => INK, 'button_background_color' => GOLD, 'button_text_color' => INK, 'border_radius' => box(2, 2, 2, 2)]),
        button('Back to the shop', $shop_url, 'gold'),
    ]),
], ['include/singular/not_found404']);

wc_delete_product_transients();
\Elementor\Plugin::$instance->files_manager->clear_cache();
echo "done.\n";
