<?php
/** Store setup: WooCommerce/Astra/Elementor kit settings, catalog, coupons, pages and menus. */
require_once __DIR__ . '/lib.php';

echo "== Site & WooCommerce settings\n";
update_option('blogname', 'Boutique');
update_option('blogdescription', 'Premium Fashion & Luxury Essentials');
update_option('permalink_structure', '/%postname%/');
foreach ([
    'woocommerce_currency' => 'EUR', 'woocommerce_currency_pos' => 'left_space',
    'woocommerce_price_thousand_sep' => '.', 'woocommerce_price_decimal_sep' => ',', 'woocommerce_price_num_decimals' => '2',
    'woocommerce_default_country' => 'NL', 'woocommerce_store_city' => 'Amsterdam', 'woocommerce_store_address' => 'Keizersgracht 1', 'woocommerce_store_postcode' => '1015 AA',
    'woocommerce_allowed_countries' => 'all', 'woocommerce_enable_coupons' => 'yes', 'woocommerce_calc_taxes' => 'no',
    'woocommerce_coming_soon' => 'no', 'woocommerce_store_pages_only' => 'no',
    'woocommerce_thumbnail_cropping' => 'custom', 'woocommerce_thumbnail_cropping_custom_width' => '4', 'woocommerce_thumbnail_cropping_custom_height' => '5',
    'woocommerce_single_image_width' => '800', 'woocommerce_thumbnail_image_width' => '480',
    'woocommerce_enable_reviews' => 'yes', 'woocommerce_task_list_hidden' => 'yes', 'woocommerce_onboarding_profile' => ['skipped' => true],
    'woocommerce_cart_redirect_after_add' => 'no', 'woocommerce_enable_ajax_add_to_cart' => 'yes',
] as $k => $v) update_option($k, $v);
// Test-friendly payment methods (switch to a real gateway such as Mollie for iDEAL before going live)
update_option('woocommerce_cod_settings', ['enabled' => 'yes', 'title' => 'Pay on delivery', 'description' => 'Pay when your order arrives.']);
update_option('woocommerce_bacs_settings', ['enabled' => 'yes', 'title' => 'Bank transfer', 'description' => 'Transfer the amount to our bank account. Your order ships once payment is received.']);
WC_Install::create_pages();

echo "== Astra settings\n";
$astra = get_option('astra-settings', []);
$astra = array_merge($astra, [
    'ast-site-content-layout' => 'full-width-container', 'site-content-style' => 'unboxed', 'site-sidebar-layout' => 'no-sidebar',
    'woocommerce-ast-content-layout' => 'full-width-container', 'woocommerce-content-style' => 'unboxed', 'woocommerce-sidebar-layout' => 'no-sidebar',
    'single-product-ast-content-layout' => 'full-width-container', 'single-product-sidebar-layout' => 'no-sidebar',
    'archive-product-ast-content-layout' => 'full-width-container', 'archive-product-sidebar-layout' => 'no-sidebar',
    'single-page-ast-content-layout' => 'full-width-container', 'single-page-sidebar-layout' => 'no-sidebar',
    'body-font-family' => "'Jost', sans-serif", 'headings-font-family' => "'Jost', sans-serif",
    'site-layout-outside-bg-obj-responsive' => ['desktop' => ['background-color' => '#FFFFFF', 'background-type' => 'color']],
    'scroll-to-top-enable' => false,
    'global-color-palette' => ['palette' => ['#0B0B0B', '#C9A45C', '#0B0B0B', '#3A3A3A', '#F6F4F1', '#FFFFFF', '#E8CD8E', '#2A2A2A', '#000000']],
    'button-radius-fields' => ['desktop' => ['top' => 2, 'right' => 2, 'bottom' => 2, 'left' => 2], 'tablet' => [], 'mobile' => [], 'desktop-unit' => 'px', 'tablet-unit' => 'px', 'mobile-unit' => 'px'],
]);
update_option('astra-settings', $astra);

echo "== Elementor global kit (colors, fonts, buttons, custom CSS)\n";
update_option('elementor_disable_color_schemes', 'yes');
update_option('elementor_disable_typography_schemes', 'yes');
update_option('elementor_cpt_support', ['post', 'page', 'product']);
update_option('elementor_load_fa4_shim', '');
$kit_id = (int)get_option('elementor_active_kit');
$kit_css = file_get_contents(__DIR__ . '/kit.css');
$kit = \Elementor\Plugin::$instance->documents->get($kit_id, false);
$kit->save(['settings' => array_merge($kit->get_settings() ?: [], [
    'system_colors' => [
        ['_id' => 'primary', 'title' => 'Ink', 'color' => INK],
        ['_id' => 'secondary', 'title' => 'Gold', 'color' => GOLD],
        ['_id' => 'text', 'title' => 'Text', 'color' => '#3A3A3A'],
        ['_id' => 'accent', 'title' => 'Gold Light', 'color' => GOLD_LIGHT],
    ],
    'custom_colors' => [
        ['_id' => 'soft', 'title' => 'Soft Background', 'color' => SOFT],
        ['_id' => 'white', 'title' => 'White', 'color' => '#FFFFFF'],
        ['_id' => 'sale', 'title' => 'Sale Red', 'color' => '#B3261E'],
    ],
    'system_typography' => [
        ['_id' => 'primary', 'title' => 'Headings'] + typo('typography', 40, '600'),
        ['_id' => 'secondary', 'title' => 'Eyebrow'] + typo('typography', 14, '500', 3, 'uppercase'),
        ['_id' => 'text', 'title' => 'Body'] + typo('typography', 16, '400', null, null, 1.6),
        ['_id' => 'accent', 'title' => 'Buttons'] + typo('typography', 14, '600', 2.5, 'uppercase'),
    ],
    'body_color' => '#3A3A3A', 'body_background_background' => 'classic', 'body_background_color' => '#FFFFFF',
    'h1_color' => INK, 'h2_color' => INK, 'h3_color' => INK, 'link_normal_color' => INK, 'link_hover_color' => GOLD,
    'container_width' => sz(1320), 'space_between_widgets' => gap(16),
    'container_padding' => box(0, 20, 0, 20),
    'button_background_color' => INK, 'button_text_color' => '#FFFFFF', 'button_hover_background_color' => GOLD, 'button_hover_text_color' => INK,
    'button_border_radius' => box(2, 2, 2, 2), 'button_padding' => box(16, 30, 16, 30),
    'site_name' => 'Boutique', 'site_description' => 'Premium Fashion & Luxury Essentials',
    'custom_css' => $kit_css,
])]);
foreach (['body_typography', 'h1_typography', 'h2_typography'] as $p) { /* fonts come from system typography + kit CSS */ }

echo "== Product attribute: Size\n";
$attr_id = wc_attribute_taxonomy_id_by_name('size');
if (!$attr_id) {
    $attr_id = wc_create_attribute(['name' => 'Size', 'slug' => 'size', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
    delete_transient('wc_attribute_taxonomies');
}
register_taxonomy('pa_size', ['product'], ['hierarchical' => false]);
$clothing_sizes = ['S', 'M', 'L', 'XL', 'XXL'];
$shoe_sizes = ['40', '41', '42', '43', '44', '45'];
foreach (array_merge($clothing_sizes, $shoe_sizes) as $i => $s) {
    if (!term_exists($s, 'pa_size')) { $t = wp_insert_term($s, 'pa_size', ['slug' => strtolower($s)]); }
}

echo "== Categories\n";
$cat_defs = [
    'men' => ['Men', null, 'jacket', '#1d1d1d'], 'women' => ['Women', null, 'bag', '#6b4a33'],
    'shoes' => ['Shoes', null, 'shoe', '#1c1c1c'], 't-shirts' => ['T-Shirts', null, 'tshirt', '#f4f2ee'],
    'jackets' => ['Jackets', null, 'jacket', '#2b2b2e'], 'pants' => ['Pants', null, 'pants', '#3d4a35'],
    'bags' => ['Bags', null, 'bag', '#7a5236'], 'accessories' => ['Accessories', null, 'cap', '#c2b49c'],
];
$cats = [];
foreach ($cat_defs as $slug => [$name, $parent, $shape, $color]) {
    $t = term_exists($slug, 'product_cat') ?: wp_insert_term($name, 'product_cat', ['slug' => $slug]);
    $cats[$slug] = (int)$t['term_id'];
    $img = media_image("cat-$slug", 600, 750, '#EFECE7', '#E2DDD5', $shape, $color, ['light' => $color === '#f4f2ee', 'scale' => .78, 'fur' => $slug === 'men' ? '#8a7d6b' : null, 'quilt' => $slug === 'women'], "Category $name");
    update_term_meta($cats[$slug], 'thumbnail_id', $img);
}
bstate('cats', $cats);
// hide "Uncategorized" from menus/tiles by renaming it
$unc = get_term_by('slug', 'uncategorized', 'product_cat');
if ($unc) wp_update_term($unc->term_id, 'product_cat', ['name' => 'Other']);

echo "== Products\n";
// [name, cats, regular, sale, shape, color, sizes(null|clothing|shoes), image options, featured]
$products = [
    ['Hooded Down Jacket with Fur Collar – Black', ['jackets', 'men'], 349.99, 264.99, 'jacket', '#161616', 'clothing', ['fur' => '#5b5247'], true],
    ['Hooded Down Jacket with Fur Collar – Anthracite', ['jackets', 'women'], 349.99, 264.99, 'jacket', '#3a3d42', 'clothing', ['fur' => '#8b8072'], true],
    ['Cold-Weather Down Parka with Hood – Brown', ['jackets', 'men'], 349.99, 264.99, 'jacket', '#5a4030', 'clothing', [], true],
    ['Cold-Weather Down Parka with Hood – Black', ['jackets', 'women'], 349.99, 264.99, 'jacket', '#1b1b1b', 'clothing', [], true],
    ['Cloud Runner Sneaker – White Grey with Navy', ['shoes', 'men'], 169.99, 79.99, 'shoe', '#E4E4E6', 'shoes', ['light' => true, 'accent' => '#22304a', 'sole' => '#ffffff'], false],
    ['Summer Walk Loafer – Black Suede', ['shoes', 'men'], 219.99, 159.99, 'shoe', '#232323', 'shoes', ['sole' => '#d9d3c7'], false],
    ['Summer Walk Loafer – Navy Suede', ['shoes', 'women'], 219.99, 159.99, 'shoe', '#22304a', 'shoes', ['sole' => '#d9d3c7'], false],
    ['Knit Weekend Sneaker – Black', ['shoes', 'men'], 179.99, null, 'shoe', '#1e1e1e', 'shoes', ['sole' => '#f5f5f5'], false],
    ['Quilted Rain Boot – Black with Cream Logo', ['shoes', 'women'], 299.99, 219.99, 'boot', '#151515', 'shoes', ['sole' => '#efe6d2', 'accent' => '#efe6d2'], false],
    ['Cloud Runner Sneaker – Light Grey with Anthracite', ['shoes', 'women'], 169.99, 79.99, 'shoe', '#C9CBCF', 'shoes', ['light' => true, 'accent' => '#3a3d42', 'sole' => '#ffffff'], false],
    ['Essential Logo T-Shirt – White', ['t-shirts', 'men'], 69.99, null, 'tshirt', '#F7F6F3', 'clothing', ['light' => true, 'print' => '#111111'], false],
    ['Essential Logo T-Shirt – Black', ['t-shirts', 'men'], 69.99, 49.99, 'tshirt', '#161616', 'clothing', ['print' => GOLD], false],
    ['Oversized Graphic Tee – Sand', ['t-shirts', 'women'], 79.99, null, 'tshirt', '#D8C8AE', 'clothing', ['light' => true, 'print' => '#5a4030'], false],
    ['Tailored Cargo Pants – Olive', ['pants', 'men'], 129.99, null, 'pants', '#4a5640', 'clothing', ['pockets' => true], false],
    ['Slim Fit Denim Jeans – Indigo', ['pants', 'women'], 119.99, 89.99, 'pants', '#2c3d5e', 'clothing', [], false],
    ['Monogram Leather Tote Bag – Cognac', ['bags', 'women'], 449.99, null, 'bag', '#8a5a34', null, [], false],
    ['Quilted Crossbody Bag – Black', ['bags', 'women'], 389.99, 299.99, 'bag', '#141414', null, ['quilt' => true], false],
    ['Wool Logo Beanie – Black', ['accessories', 'men'], 49.99, null, 'beanie', '#1d1d1d', null, [], false],
    ['Classic Baseball Cap – Beige', ['accessories', 'women'], 59.99, 44.99, 'cap', '#CDBB9C', null, ['light' => true], false],
];
$bgs = [['#F1EEE9', '#E4DFD7'], ['#EEF0F1', '#DFE3E6'], ['#F3EFE8', '#E6DECF']];
$pids = bstate('products') ?: [];
foreach ($products as $i => [$name, $pcats, $reg, $sale, $shape, $color, $sizes, $iopts, $featured]) {
    $slug = sanitize_title($name);
    $bg = $bgs[$i % 3];
    $img = media_image("prod-$slug", 800, 1000, $bg[0], $bg[1], $shape, $color, $iopts + ['scale' => .80], $name);
    $img2 = media_image("prod-$slug-b", 800, 1000, $bg[1], $bg[0], $shape, $color, $iopts + ['scale' => .55, 'dy' => .04], "$name detail");
    $id = $pids[$slug] ?? 0;
    $product = ($id && get_post($id)) ? wc_get_product($id) : ($sizes ? new WC_Product_Variable() : new WC_Product_Simple());
    $product->set_name($name);
    $product->set_slug($slug);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_featured($featured);
    $product->set_category_ids(array_map(fn($c) => $cats[$c], $pcats));
    $product->set_image_id($img);
    $product->set_gallery_image_ids([$img2]);
    $product->set_short_description('<p>Premium quality, carefully selected for a perfect fit. In stock items ship within 24 hours.</p><ul><li>Premium materials</li><li>True to size</li><li>Free exchange within 3 days</li></ul>');
    $product->set_description("<p>The <strong>$name</strong> combines refined craftsmanship with everyday comfort. Designed to be worn season after season, it pairs effortlessly with the rest of your wardrobe.</p><p>Limited numbers per size — once it's gone, it's gone.</p>");
    $product->set_sku('BTQ-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT));
    if (!$sizes) {
        $product->set_regular_price($reg);
        $product->set_sale_price($sale ?: '');
        $product->set_manage_stock(true);
        $product->set_stock_quantity(8);
    }
    if ($sizes) {
        $opts = $sizes === 'shoes' ? $shoe_sizes : $clothing_sizes;
        $attr = new WC_Product_Attribute();
        $attr->set_id($attr_id);
        $attr->set_name('pa_size');
        $attr->set_options(array_map(fn($s) => get_term_by('slug', strtolower($s), 'pa_size')->term_id, $opts));
        $attr->set_visible(true);
        $attr->set_variation(true);
        $product->set_attributes([$attr]);
    }
    $id = $product->save();
    $pids[$slug] = $id;
    if ($sizes) {
        $existing = [];
        foreach ($product->get_children() as $vid) { $v = wc_get_product($vid); $existing[$v->get_attribute('pa_size')] = $vid; }
        foreach ($opts as $s) {
            $v = isset($existing[$s]) ? wc_get_product($existing[$s]) : new WC_Product_Variation();
            $v->set_parent_id($id);
            $v->set_attributes(['pa_size' => strtolower($s)]);
            $v->set_regular_price($reg);
            $v->set_sale_price($sale ?: '');
            $v->set_manage_stock(true);
            $v->set_stock_quantity(4);
            $v->set_status('publish');
            $v->save();
        }
        WC_Product_Variable::sync($id);
    }
    echo "  - $name (#$id)\n";
}
bstate('products', $pids);
wc_delete_product_transients();
delete_transient('wc_products_onsale');

echo "== Coupons\n";
foreach ([['WELCOME10', 10, 'Newsletter welcome discount'], ['WINTER15', 15, 'Winter drop campaign']] as [$code, $amt, $desc]) {
    $cid = wc_get_coupon_id_by_code($code);
    $c = new WC_Coupon($cid ?: 0);
    $c->set_code($code); $c->set_discount_type('percent'); $c->set_amount($amt); $c->set_description($desc);
    $c->save();
}

echo "== Simple content pages\n";
$pages = [
    'about' => 'About Us', 'faq' => 'FAQ', 'shipping' => 'Shipping & Delivery', 'returns' => 'Returns & Exchanges',
    'contact' => 'Contact', 'sale' => 'Sale', 'terms' => 'Terms & Conditions',
];
foreach ($pages as $k => $title) {
    $slug = sanitize_title($title);
    $p = get_page_by_path($slug);
    $id = $p ? $p->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug]);
    bstate("page_$k", $id);
}
$home = get_page_by_path('home');
$home_id = $home ? $home->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home', 'post_name' => 'home']);
bstate('page_home', $home_id);
update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);
$priv = get_option('wp_page_for_privacy_policy');
if ($priv) wp_update_post(['ID' => $priv, 'post_status' => 'publish']);
// Remove default demo content
if ($p = get_page_by_path('sample-page')) wp_trash_post($p->ID);
if ($p = get_post(1)) if ($p->post_name === 'hello-world') wp_trash_post(1);

echo "== Menus\n";
function build_menu($name, $location, array $items) {
    $menu = wp_get_nav_menu_object($name);
    $mid = $menu ? $menu->term_id : wp_create_nav_menu($name);
    foreach (wp_get_nav_menu_items($mid) ?: [] as $it) wp_delete_post($it->ID, true);
    $add = function ($items, $parent = 0) use (&$add, $mid) {
        foreach ($items as $it) {
            $args = ['menu-item-title' => $it[0], 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent];
            if ($it[1] === 'cat') $args += ['menu-item-type' => 'taxonomy', 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $it[2]];
            elseif ($it[1] === 'page') $args += ['menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $it[2]];
            else $args += ['menu-item-type' => 'custom', 'menu-item-url' => $it[2]];
            $iid = wp_update_nav_menu_item($mid, 0, $args);
            if (!empty($it[3])) $add($it[3], $iid);
        }
    };
    $add($items);
    if ($location) { $loc = get_theme_mod('nav_menu_locations', []); $loc[$location] = $mid; set_theme_mod('nav_menu_locations', $loc); }
    return get_term($mid)->slug;
}
$shop = wc_get_page_id('shop');
$sub = fn() => [['Jackets', 'cat', $cats['jackets']], ['T-Shirts', 'cat', $cats['t-shirts']], ['Pants', 'cat', $cats['pants']], ['Shoes', 'cat', $cats['shoes']], ['Accessories', 'cat', $cats['accessories']]];
bstate('menu_main', build_menu('Main Menu', 'primary', [
    ['New In', 'page', $shop],
    ['Men', 'cat', $cats['men'], $sub()],
    ['Women', 'cat', $cats['women'], array_merge([['Bags', 'cat', $cats['bags']]], $sub())],
    ['Jackets', 'cat', $cats['jackets']],
    ['Shoes', 'cat', $cats['shoes']],
    ['Bags', 'cat', $cats['bags']],
    ['Sale', 'page', bstate('page_sale')],
]));
bstate('menu_service', build_menu('Customer Service', null, [
    ['FAQ', 'page', bstate('page_faq')], ['Shipping & Delivery', 'page', bstate('page_shipping')], ['Returns & Exchanges', 'page', bstate('page_returns')],
    ['Contact', 'page', bstate('page_contact')], ['My Account', 'page', wc_get_page_id('myaccount')],
]));
bstate('menu_shop', build_menu('Shop', null, [
    ['Men', 'cat', $cats['men']], ['Women', 'cat', $cats['women']], ['Jackets', 'cat', $cats['jackets']], ['Shoes', 'cat', $cats['shoes']], ['Bags', 'cat', $cats['bags']], ['Sale', 'page', bstate('page_sale')],
]));
echo "done.\n";
