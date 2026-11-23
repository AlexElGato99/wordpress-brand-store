<?php
/** Builds every Elementor / Elementor Pro template and page of the Boutique store. */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/icons.php';
// Fill {{icon:name}} placeholders in HTML snippets with inline SVG icons.
$with_icons = fn($html) => preg_replace_callback('/\{\{icon:([a-z]+)\}\}/', fn($m) => lmx_icon($m[1], 18), $html);

$cats = bstate('cats');
$shop_url = get_permalink(wc_get_page_id('shop'));
$cat_url = fn($slug) => get_term_link($cats[$slug], 'product_cat');
$page_url = fn($k) => get_permalink(bstate("page_$k"));
$account_url = get_permalink(wc_get_page_id('myaccount'));
$tt = fn($slug) => (int)get_term($cats[$slug], 'product_cat')->term_taxonomy_id;
// Brand phone number (also used for WhatsApp). Changed 2026-11-23.
$PHONE = '+31 6 43 58 43 28';
$TEL = 'tel:+31643584328';
$WHATSAPP = 'https://wa.me/31643584328';
$EMAIL = 'info@lmxboutique.nl';
$INSTAGRAM = 'https://www.instagram.com/lmxboutique/';
$TIKTOK = 'https://www.tiktok.com/@dripbylmx';

/* ---------------- Imagery (real photos only) ----------------
 * Optional own photos: set an attachment ID with bstate('photo_hero', ID) / bstate('photo_about', ID)
 * and re-run this script. Without them the sections fall back to a solid/gradient background. */
$hero = (int)bstate('photo_hero');
$hero_pos = bstate('photo_hero_position') ?: 'center right';
// Main image of a product in a category (newest first), used for tiles/gallery when no category image exists.
$product_image = function ($term_ids, $offset = 0) {
    $q = new WP_Query(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1, 'offset' => $offset, 'fields' => 'ids', 'no_found_rows' => true,
        'tax_query' => [['taxonomy' => 'product_cat', 'terms' => (array)$term_ids, 'include_children' => true]], 'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']]]);
    return $q->posts ? (int)get_post_thumbnail_id($q->posts[0]) : 0;
};
$cat_image = function ($slug) use ($cats, $product_image) {
    $id = (int)get_term_meta($cats[$slug], 'thumbnail_id', true);
    return ($id && get_post($id)) ? $id : $product_image($cats[$slug]);
};
$looks = [];
$q = new WP_Query(['post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 6, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'date', 'order' => 'DESC',
    'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']], 'tax_query' => [['taxonomy' => 'product_cat', 'terms' => array_values(array_filter([$cats['jackets'] ?? 0, $cats['bags'] ?? 0, $cats['shoes'] ?? 0])), 'include_children' => true]]]);
foreach ($q->posts as $pid) $looks[] = (int)get_post_thumbnail_id($pid);
$about_img = (int)bstate('photo_about') ?: ($looks[0] ?? 0);
// Winter drop uses the Winter Jackets category when it exists (down jackets), otherwise all jackets.
$winter = get_term_by('slug', 'winterjassen', 'product_cat') ?: get_term($cats['jackets'], 'product_cat');
// Lowest current price in that category, for the winter-drop headline.
$jackets_from = (function () use ($winter) {
    $min = null;
    foreach (wc_get_products(['status' => 'publish', 'limit' => -1, 'category' => [$winter->slug], 'return' => 'objects']) as $p) {
        $pr = (float)$p->get_price(); if ($pr > 0 && ($min === null || $pr < $min)) $min = $pr;
    }
    return $min ? '€' . number_format($min, 2, ',', '.') : null;
})();

/* ---------------- HEADER ---------------- */
$announcement = con([
    'content_width' => 'full', 'flex_direction' => 'row', 'flex_justify_content' => 'center', 'flex_align_items' => 'center',
    'background_background' => 'classic', 'background_color' => INK, 'padding' => box(9, 16, 9, 16), 'min_height' => sz(36),
], [
    w('text-editor', array_merge([
        'editor' => '<p><span class="btq-badge">EXTRA DISCOUNT</span><strong style="color:#E8CD8E">15% extra</strong><span class="btq-hide-m"> on the entire collection</span> with code <span class="btq-code" style="color:#E8CD8E">WINTER15</span> <a href="' . esc_url($shop_url) . '" style="color:#E8CD8E;text-decoration:underline">Shop<span class="btq-hide-m"> now</span> ›</a></p>',
        'text_color' => '#FFFFFF', 'align' => 'center',
        'typography_font_size_mobile' => sz(11),
    ], typo('typography', 13, '400', 0.5))),
]);
$header_main = con([
    'content_width' => 'boxed', 'boxed_width' => sz(1440), 'flex_direction' => 'row', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center',
    'background_background' => 'classic', 'background_color' => '#FFFFFF', 'padding' => box(14, 20, 14, 20), 'padding_mobile' => box(10, 12, 10, 12),
    'flex_gap' => gap(10), 'flex_gap_mobile' => gap(0), 'flex_wrap' => 'nowrap', '_css_classes' => 'btq-h-row',
], [
    // logo: left on desktop, centre on tablet/mobile (order via .btq-h-* CSS)
    con(['content_width' => 'full', 'width' => sz(20, '%'), 'width_tablet' => sz(56, '%'), 'width_mobile' => sz(56, '%'), 'flex_direction' => 'row', 'flex_justify_content' => 'flex-start', 'flex_justify_content_tablet' => 'center', '_css_classes' => 'btq-h-logo', 'padding' => box(0, 0, 0, 0)], [
        w('theme-site-title', array_merge(['__dynamic__' => ['title' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text(null, 'site-title'), 'link' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text(null, 'site-url')], 'align' => 'left', 'align_tablet' => 'center', 'title_color' => INK, 'header_size' => 'div',
            'typography_font_size_mobile' => sz(19), 'typography_letter_spacing_mobile' => sz(3)], typo('typography', 24, '700', 4, 'uppercase', 1))),
    ], true),
    // navigation: centre on desktop, hamburger on the left on tablet/mobile
    con(['content_width' => 'full', 'width' => sz(58, '%'), 'width_tablet' => sz(22, '%'), 'width_mobile' => sz(22, '%'), 'flex_direction' => 'row', 'flex_justify_content' => 'center', 'flex_align_items' => 'center', '_css_classes' => 'btq-h-nav', 'padding' => box(0, 0, 0, 0)], [
        w('nav-menu', array_merge([
            'menu' => bstate('menu_main'), 'layout' => 'horizontal', 'align_items' => 'center', 'pointer' => 'underline', 'animation_line' => 'fade',
            'submenu_icon' => ico('fas fa-chevron-down'), 'dropdown' => 'tablet', 'toggle' => 'burger', 'toggle_align' => 'left', 'full_width' => 'stretch',
            'color_menu_item' => INK, 'color_menu_item_hover' => GOLD, 'pointer_color_menu_item_hover' => GOLD, 'color_menu_item_active' => INK,
            'padding_horizontal_menu_item' => sz(9), 'padding_horizontal_menu_item_laptop' => sz(7), 'menu_space_between' => sz(0),
            'color_dropdown_item' => INK, 'background_color_dropdown_item' => '#FFFFFF', 'color_dropdown_item_hover' => '#FFFFFF', 'background_color_dropdown_item_hover' => INK,
            'toggle_color' => INK, 'toggle_background_color' => 'rgba(0,0,0,0)', 'toggle_size' => sz(24),
            'padding_horizontal_dropdown_item' => sz(24), 'padding_vertical_dropdown_item' => sz(14),
        ], typo('menu_typography', 13, '500', 1.2, 'uppercase'), typo('dropdown_typography', 13, '400', 1, 'uppercase'))),
    ], true),
    // right: search, account, cart
    con(['content_width' => 'full', 'width' => sz(22, '%'), 'width_tablet' => sz(22, '%'), 'width_mobile' => sz(22, '%'), 'flex_direction' => 'row', 'flex_justify_content' => 'flex-end', '_css_classes' => 'btq-h-icons', 'flex_align_items' => 'center', 'flex_gap' => gap(14), 'flex_gap_mobile' => gap(4), 'padding' => box(0, 0, 0, 0)], [
        w('search-form', ['skin' => 'full_screen', 'placeholder' => 'Search products…', 'toggle_color' => INK, 'toggle_background_color' => '#FFFFFF', 'toggle_color_hover' => GOLD, 'toggle_background_color_hover' => '#FFFFFF', 'toggle_size' => sz(36), 'toggle_icon_size' => sz(17), '_element_width' => 'auto', 'hide_mobile' => 'hidden-mobile']),
        w('icon', ['selected_icon' => ico('far fa-user', 'fa-regular'), 'link' => lnk($account_url), 'primary_color' => INK, 'size' => sz(19), '_element_width' => 'auto', 'hide_mobile' => 'hidden-mobile']),
        w('woocommerce-menu-cart', [
            'icon' => 'bag-light', 'items_indicator' => 'bubble', 'show_subtotal' => 'yes', 'hide_empty_indicator' => '',
            'toggle_button_text_color' => INK, 'toggle_button_icon_color' => INK, 'toggle_button_background_color' => '#FFFFFF', 'toggle_button_hover_background_color' => '#FFFFFF', 'toggle_button_hover_text_color' => GOLD, 'toggle_button_hover_icon_color' => GOLD, 'toggle_button_border_width' => sz(0), 'toggle_button_padding' => box(6, 4, 6, 4),
            'items_indicator_text_color' => INK, 'items_indicator_background_color' => GOLD, 'toggle_icon_size' => sz(22),
            'subtotal_typography_typography' => 'custom', 'subtotal_typography_font_family' => FONT, 'subtotal_typography_font_size' => sz(14), 'subtotal_typography_font_weight' => '500',
            'checkout_button_background_color' => INK, 'view_cart_button_background_color' => SOFT, 'view_cart_button_text_color' => INK,
            '_element_width' => 'auto', 'show_subtotal_mobile' => '',
        ]),
    ], true),
]);
el_template('header', 'header', 'LMX Header', [
    con(['content_width' => 'full', 'flex_direction' => 'column', 'padding' => box(0, 0, 0, 0), 'flex_gap' => gap(0),
         '_css_classes' => 'btq-header'], [$header_main]),   // discount bar removed (user request)
], ['include/general']);
echo "header ok\n";

/* ---------------- FOOTER ---------------- */
$fhead = fn($t) => w('heading', array_merge(['title' => $t, 'header_size' => 'h4', 'title_color' => GOLD, '_css_classes' => 'btq-fhead'], typo('typography', 12, '600', 3.6, 'uppercase', 1.2)));
$footer_link_list = fn($items) => w('icon-list', array_merge([
    'icon_list' => array_map(fn($i) => ['_id' => eid(), 'text' => $i[0], 'link' => lnk($i[1]), 'selected_icon' => ico('')], $items),
    'space_between' => sz(12), 'text_color' => '#BDB6AA', 'text_color_hover' => GOLD_LIGHT, 'icon_self_align' => 'left', '_css_classes' => 'btq-flinks',
], typo('icon_typography', 14, '400', 0.3, null, 1.5)));
el_template('footer', 'footer', 'LMX Footer', [
    // USP bar
    con(['content_width' => 'boxed', 'container_type' => 'grid', 'grid_columns_grid' => sz(4, 'fr'), 'grid_columns_grid_tablet' => sz(2, 'fr'), 'grid_columns_grid_mobile' => sz(1, 'fr'),
         'grid_rows_grid' => sz(1, 'fr'), 'grid_gaps' => gap(24), 'padding' => box(48, 20, 48, 20), 'background_background' => 'classic', 'background_color' => SOFT, '_css_classes' => 'btq-pattern-light btq-usp'], array_map(fn($u) =>
        w('icon-box', array_merge([
            'selected_icon' => ico($u[0]), 'title_text' => $u[1], 'description_text' => $u[2], 'position' => 'inline-start', 'title_size' => 'h4',
            'primary_color' => INK, 'icon_size' => sz(28), 'icon_space' => sz(16), 'title_color' => INK, 'description_color' => '#6B6B6B', 'title_bottom_space' => sz(4),
        ], typo('title_typography', 16, '600'), typo('description_typography', 14, '400'))),
        [['fas fa-shipping-fast', 'Order before 16:00', 'Ordered today, delivered tomorrow'], ['fas fa-exchange-alt', 'Exchange guarantee', 'Exchange within 3 days'],
         ['fas fa-lock', 'Secure payment', 'Pay safely with every bank'], ['fas fa-award', 'Satisfaction guarantee', '100% satisfaction guaranteed']])),
    // main footer
    con(['content_width' => 'full', 'flex_direction' => 'column', 'background_background' => 'classic', 'background_color' => INK, 'padding' => box(84, 20, 34, 20), 'padding_mobile' => box(56, 20, 30, 20), 'flex_gap' => gap(52), '_css_classes' => 'btq-footer'], [
        con(['content_width' => 'boxed', 'container_type' => 'grid', 'grid_columns_grid' => ['unit' => 'custom', 'size' => '1.4fr 1fr 1fr 1fr'], 'grid_columns_grid_tablet' => sz(2, 'fr'), 'grid_columns_grid_mobile' => sz(1, 'fr'),
             'grid_rows_grid' => sz(1, 'fr'), 'grid_gaps' => gap(40), 'padding' => box(0, 0, 0, 0)], [
            con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(14), 'padding' => box(0, 0, 0, 0)], [
                w('html', ['html' => '<a class="lmx-flogo" href="' . esc_url(home_url('/')) . '" aria-label="LMX Boutique home"><span class="lmx-flogo-mark">LMX</span><span class="lmx-flogo-sub">Boutique</span></a>']),
                w('text-editor', array_merge(['editor' => '<p>Premium Fashion &amp; Luxury Essentials. Carefully selected designer clothing, shoes and accessories for people with style.</p>', 'text_color' => '#A39C91', '_css_classes' => 'btq-fdesc'], typo('typography', 14, '400', 0.2, null, 1.85))),
                w('icon-list', array_merge(['icon_list' => [
                    ['_id' => eid(), 'text' => $EMAIL, 'selected_icon' => ico('far fa-envelope', 'fa-regular'), 'link' => lnk('mailto:' . $EMAIL)],
                    ['_id' => eid(), 'text' => 'WhatsApp Customer Service', 'selected_icon' => ico('fab fa-whatsapp', 'fa-brands'), 'link' => lnk($WHATSAPP)],
                    ['_id' => eid(), 'text' => $PHONE, 'selected_icon' => ico('fas fa-mobile-alt'), 'link' => lnk($TEL)],
                ], 'icon_color' => GOLD, 'text_color' => '#E9E4DC', 'text_color_hover' => GOLD_LIGHT, 'space_between' => sz(12), 'icon_size' => sz(14), 'text_indent' => sz(10)], typo('icon_typography', 14, '400', 0.3))),
                w('social-icons', ['social_icon_list' => [
                    ['_id' => eid(), 'social_icon' => ico('fab fa-instagram', 'fa-brands'), 'link' => lnk($INSTAGRAM)],
                    ['_id' => eid(), 'social_icon' => ico('fab fa-tiktok', 'fa-brands'), 'link' => lnk($TIKTOK)],
                    ['_id' => eid(), 'social_icon' => ico('fab fa-whatsapp', 'fa-brands'), 'link' => lnk($WHATSAPP)],
                ], 'icon_color' => 'custom', 'icon_primary_color' => '#1E1C1A', 'icon_secondary_color' => GOLD_LIGHT, 'icon_size' => sz(15), 'icon_padding' => ['unit' => 'em', 'size' => 0.7], 'align' => 'left', 'shape' => 'circle']),
            ], true),
            con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(18), 'padding' => box(0, 0, 0, 0)], [
                $fhead('Customer Service'),
                $footer_link_list([['FAQ', $page_url('faq')], ['Shipping & Delivery', $page_url('shipping')], ['Returns & Exchanges', $page_url('returns')], ['Contact', $page_url('contact')], ['My Account', $account_url]]),
            ], true),
            con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(18), 'padding' => box(0, 0, 0, 0)], [
                $fhead('Why LMX Boutique?'),
                w('icon-list', array_merge(['icon_list' => array_map(fn($t) => ['_id' => eid(), 'text' => $t, 'selected_icon' => ico('fas fa-check')],
                    ['Premium quality', 'Fast shipping', 'Secure payment', 'Personal customer service']),
                    'icon_color' => GOLD, 'text_color' => '#BDB6AA', 'space_between' => sz(12), 'icon_size' => sz(11), 'text_indent' => sz(10)], typo('icon_typography', 14, '400', 0.3, null, 1.5))),
            ], true),
            con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(18), 'padding' => box(0, 0, 0, 0)], [
                $fhead('Pay securely with'),
                w('html', ['html' => file_get_contents(__DIR__ . '/payment-icons.html')]),
                w('text-editor', array_merge(['editor' => '<p>All payments are encrypted and processed securely.</p>', 'text_color' => '#857F76'], typo('typography', 12.5, '400', 0.2, null, 1.7))),
            ], true),
        ]),
        con(['content_width' => 'boxed', 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'center',
             'border_border' => 'solid', 'border_width' => box(1, 0, 0, 0), 'border_color' => 'rgba(201,164,92,.18)', 'padding' => box(28, 0, 0, 0), 'flex_gap' => gap(10)], [
            w('text-editor', array_merge(['editor' => '<p>© ' . date('Y') . ' LMX Boutique — Premium Fashion &amp; Luxury Essentials. All rights reserved.</p>', 'text_color' => '#7E786F'], typo('typography', 12.5, '400', 0.3))),
            w('heading', array_merge(['title' => 'Wear confidence. Wear LMX.', 'header_size' => 'p', 'title_color' => GOLD, '_css_classes' => ''], typo('typography', 11.5, '500', 3.2, 'uppercase'))),
        ]),
    ]),
    // floating WhatsApp button
    w('icon', ['selected_icon' => ico('fab fa-whatsapp', 'fa-brands'), 'view' => 'stacked', 'shape' => 'circle', 'primary_color' => '#25D366', 'secondary_color' => '#FFFFFF',
               'size' => sz(28), 'icon_padding' => sz(16), 'link' => lnk($WHATSAPP), '_css_classes' => 'btq-whatsapp', '_position' => 'fixed', '_offset_orientation_h' => 'end', '_offset_x_end' => sz(22), '_offset_y' => sz(22), '_offset_orientation_v' => 'end', '_z_index' => 999, '_element_width' => 'auto']),
], ['include/general']);
echo "footer ok\n";

/* ---------------- SINGLE PRODUCT ---------------- */
$usp_list = w('icon-list', array_merge(['icon_list' => [
    ['_id' => eid(), 'text' => 'In stock = shipped within 24 hours', 'selected_icon' => ico('fas fa-shipping-fast')],
    ['_id' => eid(), 'text' => 'Free exchange within 3 days', 'selected_icon' => ico('fas fa-exchange-alt')],
    ['_id' => eid(), 'text' => 'Secure payment with iDEAL, card, PayPal & Bancontact', 'selected_icon' => ico('fas fa-lock')],
    ['_id' => eid(), 'text' => 'Questions? WhatsApp us', 'selected_icon' => ico('fab fa-whatsapp', 'fa-brands'), 'link' => lnk($WHATSAPP)],
], 'icon_color' => GOLD, 'text_color' => '#3A3A3A', 'space_between' => sz(12), 'icon_size' => sz(15), 'text_indent' => sz(8)], typo('icon_typography', 14, '400')));
el_template('single-product', 'product', 'LMX Single Product', [
    con(['content_width' => 'boxed', 'flex_direction' => 'column', 'padding' => box(24, 20, 10, 20)], [
        w('woocommerce-breadcrumb', ['text_color' => '#8A8A8A', 'link_color' => INK] + typo('text_typography', 13, '400', 0.5)),
    ]),
    con(['content_width' => 'boxed', 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_gap' => gap(60), 'flex_gap_tablet' => gap(30), 'padding' => box(10, 20, 60, 20)], [
        con(['content_width' => 'full', 'width' => sz(55, '%'), 'width_mobile' => sz(100, '%'), 'padding' => box(0, 0, 0, 0)], [
            w('woocommerce-product-images', ['sale_flash' => 'yes', 'spacing_thumbs' => sz(8)]),
        ], true),
        con(['content_width' => 'full', 'width' => sz(45, '%'), 'width_mobile' => sz(100, '%'), 'flex_direction' => 'column', 'flex_gap' => gap(14), 'padding' => box(0, 0, 0, 0),
             '_css_classes' => 'btq-sticky-col'], [
            w('heading', array_merge(['title' => 'Premium Collection', 'header_size' => 'p', 'title_color' => GOLD], typo('typography', 12, '600', 3, 'uppercase'))),
            w('woocommerce-product-title', array_merge(['__dynamic__' => ['title' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text(null, 'woocommerce-product-title-tag')], 'header_size' => 'h1', 'title_color' => INK, 'typography_font_size_mobile' => sz(24)], typo('typography', 32, '500', -0.5, null, 1.2))),
            w('woocommerce-product-rating', []),
            w('woocommerce-product-price', array_merge(['price_color' => INK, 'sale_price_color' => '#B3261E', 'sale_price_newline' => ''], typo('typography', 24, '600'))),
            w('html', ['html' => $with_icons(file_get_contents(__DIR__ . '/delivery-widget.html'))]),
            w('woocommerce-product-short-description', array_merge(['text_color' => '#555555'], typo('text_typography', 15, '400', null, null, 1.6))),
            w('woocommerce-product-add-to-cart', array_merge([
                'button_text_color' => '#FFFFFF', 'button_bg_color' => INK, 'button_text_color_hover' => INK, 'button_bg_color_hover' => GOLD,
                'button_border_radius' => box(2, 2, 2, 2), 'button_padding' => box(18, 30, 18, 30),
                'variations_space_between' => sz(12),
            ], typo('button_typography', 14, '600', 2.5, 'uppercase'))),
            w('divider', ['color' => '#EAEAEA', 'gap' => sz(6)]),
            $usp_list,
            w('woocommerce-product-meta', array_merge(['view' => 'stacked', 'text_color' => '#777'], typo('text_typography', 13, '400'))),
        ], true),
    ]),
    con(['content_width' => 'boxed', 'flex_direction' => 'column', 'padding' => box(0, 20, 40, 20)], [
        w('woocommerce-product-data-tabs', array_merge(['tab_text_color' => '#888', 'tab_active_text_color' => INK, 'tab_bg_color' => '#FFFFFF', 'tab_active_bg_color' => SOFT, 'panel_border_color' => '#EAEAEA'], typo('tab_typography', 13, '600', 2, 'uppercase'))),
    ]),
    con(['content_width' => 'boxed', 'flex_direction' => 'column', 'padding' => box(20, 20, 70, 20)], [
        w('woocommerce-product-related', array_merge(['posts_per_page' => 4, 'columns' => '4', 'columns_tablet' => '3', 'columns_mobile' => '2', 'orderby' => 'rand',
            'show_heading' => 'yes', 'heading_color' => INK, 'column_gap' => sz(20), 'row_gap' => sz(30)], typo('heading_typography', 26, '600', 1, 'uppercase'))),
    ]),
], ['include/product']);
echo "single product ok\n";

/* ---------------- PRODUCT ARCHIVE (shop + categories) ---------------- */
el_template('product-archive', 'product-archive', 'LMX Shop Archive', [
    con(['content_width' => 'boxed', 'flex_direction' => 'column', 'flex_align_items' => 'center', 'flex_gap' => gap(10),
         'background_background' => 'classic', 'background_color' => SOFT, 'padding' => box(60, 20, 50, 20), 'padding_mobile' => box(36, 16, 30, 16), '_css_classes' => 'btq-pattern-light'], [
        w('woocommerce-breadcrumb', ['text_color' => '#8A8A8A', 'link_color' => INK, 'align' => 'center'] + typo('text_typography', 13, '400', 0.5)),
        w('theme-archive-title', array_merge(['__dynamic__' => ['title' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text(null, 'archive-title', ['include_context' => ''])], 'header_size' => 'h1', 'title_color' => INK, 'align' => 'center', 'typography_font_size_mobile' => sz(30)], typo('typography', 46, '700', 2, 'uppercase', 1.1))),
        w('woocommerce-archive-description', ['align' => 'center', 'text_color' => '#666']),
    ]),
    con(['content_width' => 'boxed', 'flex_direction' => 'column', 'padding' => box(40, 20, 80, 20)], [
        w('wc-archive-products', [
            'columns' => '4', 'columns_tablet' => '3', 'columns_mobile' => '2', 'rows' => 6, 'paginate' => 'yes', 'allow_order' => 'yes', 'show_result_count' => 'yes',
            'query_post_type' => 'current_query', 'column_gap' => sz(24), 'column_gap_mobile' => sz(12), 'row_gap' => sz(40), 'nothing_found_message' => 'No products found in this category yet.',
        ]),
    ]),
], ['include/product_archive']);
echo "archive ok\n";

/* ---------------- NEWSLETTER POPUP ---------------- */
el_template('popup', 'popup', 'Newsletter Popup (10% off)', [
    con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_align_items' => 'center', 'flex_gap' => gap(14), 'padding' => box(44, 36, 36, 36), 'padding_mobile' => box(36, 20, 28, 20),
         'background_background' => 'gradient', 'background_color' => '#14110D', 'background_color_b' => '#060606', 'background_gradient_type' => 'radial',
         'border_border' => 'solid', 'border_width' => box(1, 1, 1, 1), 'border_color' => 'rgba(201,164,92,.5)', '_css_classes' => 'btq-pattern-dark'], [
        w('heading', array_merge(['title' => '10% off your', 'header_size' => 'h3', 'title_color' => '#FFFFFF', 'align' => 'center'], typo('typography', 30, '700', 0, 'uppercase', 1))),
        w('heading', array_merge(['title' => 'first order', 'header_size' => 'h3', 'align' => 'center', '_css_classes' => 'btq-gold-text', '_margin' => box(-10, 0, 0, 0)], typo('typography', 30, '700', 0, 'uppercase', 1))),
        w('text-editor', array_merge(['editor' => '<p>Subscribe and be the first to hear about exclusive deals and new drops.</p>', 'text_color' => '#CFCAC2', 'align' => 'center'], typo('typography', 15, '400'))),
        w('form', array_merge([
            'form_name' => 'Newsletter',
            'form_fields' => [['_id' => eid(), 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Email', 'placeholder' => 'Your email address', 'required' => 'true', 'width' => '100']],
            'show_labels' => '', 'button_text' => 'Subscribe', 'button_width' => '100', 'button_size' => 'md',
            'submit_actions' => ['save-to-database'],
            'success_message' => 'Welcome! Use code WELCOME10 at checkout for 10% off.', 'custom_messages' => 'yes',
            'field_background_color' => '#FFFFFF', 'field_text_color' => INK, 'field_border_radius' => box(2, 2, 2, 2),
            'button_background_color' => GOLD, 'button_text_color' => INK, 'button_background_hover_color' => GOLD_LIGHT, 'button_hover_color' => INK, 'button_border_radius' => box(2, 2, 2, 2),
            'message_color' => GOLD_LIGHT, 'success_message_color' => GOLD_LIGHT, 'row_gap' => sz(12),
        ], typo('button_typography', 14, '600', 3, 'uppercase'), typo('field_typography', 15, '400'))),
        w('text-editor', array_merge(['editor' => '<p><a href="#elementor-action%3Aaction%3Dpopup%3Aclose%26settings%3DeyJkb19ub3Rfc2hvd19hZ2FpbiI6IiJ9" style="color:#CFCAC2;text-decoration:underline">No, thanks</a></p><p>By subscribing you agree to receive emails from LMX Boutique. See our <a style="color:#E8CD8E" href="' . esc_url(get_privacy_policy_url()) . '">privacy policy</a>.</p>', 'text_color' => '#7E786F', 'align' => 'center'], typo('typography', 12, '400'))),
    ]),
], ['include/general'], [
    'width' => sz(480), 'width_mobile' => sz(92, 'vw'), 'height_type' => 'auto', 'horizontal_position' => 'center', 'vertical_position' => 'center',
    'overlay' => 'yes', 'overlay_background_background' => 'classic', 'overlay_background_color' => 'rgba(0,0,0,0.65)',
    'close_button' => 'yes', 'close_button_color' => '#FFFFFF', 'entrance_animation' => 'fadeInUp', 'entrance_animation_duration' => ['size' => 0.6, 'unit' => 'px'],
    'background_background' => 'classic', 'background_color' => '#0B0B0B', 'border_radius' => box(4, 4, 4, 4),
]);
$popup_id = bstate('tpl_popup');
update_post_meta($popup_id, '_elementor_popup_display_settings', [
    'triggers' => ['page_load' => 'yes', 'page_load_delay' => 6],
    'timing' => ['times' => 'yes', 'times_times' => 1, 'times_period' => 'day'],
]);
echo "popup ok\n";

/* ---------------- HOMEPAGE ---------------- */
$eyebrow = fn($t, $c = GOLD, $align = 'left') => w('heading', array_merge(['title' => $t, 'header_size' => 'p', 'title_color' => $c, 'align' => $align], typo('typography', 14, '500', 4, 'uppercase')));
$section_title = fn($t, $align = 'left') => w('heading', array_merge(['title' => $t, 'header_size' => 'h2', 'title_color' => INK, 'align' => $align, 'typography_font_size_mobile' => sz(24)], typo('typography', 32, '700', 1, 'uppercase', 1.15)));

$home = [];
// 1. Hero
$home[] = con([
    'content_width' => 'boxed', 'boxed_width' => sz(1440), 'flex_direction' => 'column', 'flex_justify_content' => 'center', 'flex_align_items' => 'flex-start', 'flex_gap' => gap(20),
    'min_height' => sz(86, 'vh'), 'min_height_mobile' => sz(78, 'vh'), 'padding' => box(80, 50, 80, 50), 'padding_mobile' => box(60, 20, 60, 20),
    'background_background' => $hero ? 'classic' : 'gradient', 'background_image' => img($hero), 'background_position' => $hero_pos, 'background_position_mobile' => 'center right', 'background_size' => 'cover', '_css_classes' => 'btq-hero',
    'background_color' => '#2A2119', 'background_color_b' => '#050505', 'background_gradient_type' => 'radial', 'background_gradient_position' => 'center right',
    // warm dark gradient from the left so the white text reads on a light photo; extra shading via .btq-hero in kit.css
    'background_overlay_background' => 'gradient', 'background_overlay_color' => 'rgba(14,10,7,0.86)', 'background_overlay_color_b' => 'rgba(14,10,7,0)',
    'background_overlay_gradient_angle' => sz(90, 'deg'), 'background_overlay_color_stop' => sz(0, '%'), 'background_overlay_color_b_stop' => sz(68, '%'),
], [
    $eyebrow('Premium Fashion', '#FFFFFF'),
    w('heading', array_merge(['title' => 'Drip by LMX', 'header_size' => 'h1', 'title_color' => '#FFFFFF', 'typography_font_size_tablet' => sz(56), 'typography_font_size_mobile' => sz(40)], typo('typography', 78, '700', 1, 'uppercase', 1))),
    w('text-editor', array_merge(['editor' => '<p>Exclusive clothing &amp; accessories<br>for people with style.</p>', 'text_color' => '#E8E4DD'], typo('typography', 19, '400', null, null, 1.5))),
    con(['content_width' => 'full', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => gap(14), 'padding' => box(12, 0, 0, 0)], [
        button('Shop Men', $cat_url('men'), 'gold'),
        button('Shop Women', $cat_url('women'), 'dark'),
    ], true),
]);
// 2. Marquee strip
$marquee_items = str_repeat('<span>In stock = shipped within 24 hours</span><span>Secure payment with iDEAL</span><span>Questions? WhatsApp us</span><span>Free exchange within 3 days</span>', 4);
$home[] = con(['content_width' => 'full', 'padding' => box(14, 0, 14, 0), 'background_background' => 'gradient', 'background_color' => '#B8893D', 'background_color_b' => '#E8CD8E', 'background_gradient_angle' => sz(90, 'deg'), '_css_classes' => 'btq-marquee'], [
    w('html', ['html' => '<div class="btq-track" style="font:600 13px/1 Jost,sans-serif;letter-spacing:3px;text-transform:uppercase;color:#0B0B0B">' . $marquee_items . '</div>']),
]);
// 3. Winter drop – countdown + sale jackets
$home[] = con([
    'content_width' => 'boxed', 'flex_direction' => 'column', 'flex_gap' => gap(28), 'padding' => box(90, 20, 90, 20), 'padding_mobile' => box(56, 16, 56, 16),
    'background_background' => 'gradient', 'background_color' => '#1C1712', 'background_color_b' => '#050505', 'background_gradient_type' => 'radial', 'background_gradient_position' => 'top left',
    '_css_classes' => 'btq-dark',
], [
    con(['content_width' => 'full', 'flex_direction' => 'row', 'flex_direction_tablet' => 'column', 'flex_align_items' => 'flex-end', 'flex_align_items_tablet' => 'flex-start', 'flex_justify_content' => 'space-between', 'flex_gap' => gap(40), 'padding' => box(0, 0, 0, 0)], [
        con(['content_width' => 'full', 'width' => sz(58, '%'), 'width_tablet' => sz(100, '%'), 'flex_direction' => 'column', 'flex_gap' => gap(16), 'padding' => box(0, 0, 0, 0)], [
            w('html', ['html' => '<span style="display:inline-block;border:1px solid rgba(201,164,92,.5);color:#E8CD8E;padding:8px 16px;border-radius:40px;font:500 12px/1 Jost,sans-serif;letter-spacing:3px;text-transform:uppercase">' . lmx_icon('snow', 15) . ' Winter Drop &nbsp;|&nbsp; New In</span>']),
            w('heading', array_merge(['title' => 'Designer down jackets', 'header_size' => 'h2', 'title_color' => '#FFFFFF', 'typography_font_size_mobile' => sz(34), 'typography_font_size_tablet' => sz(48)], typo('typography', 62, '800', -1, 'uppercase', 1))),
            w('heading', array_merge(['title' => $jackets_from ? 'Now from ' . $jackets_from : 'Now in stock', 'header_size' => 'p', '_css_classes' => 'btq-gold-text', 'typography_font_size_mobile' => sz(34), 'typography_font_size_tablet' => sz(48)], typo('typography', 62, '800', -1, 'uppercase', 1))),
            w('text-editor', array_merge(['editor' => '<p>New Moncler, Loro Piana &amp; Peuterey down jackets — in stock now in sizes S to XXL.<br>Limited numbers per size, so once they\'re gone, they\'re gone.</p>', 'text_color' => '#BDB6AA'], typo('typography', 17, '400', null, null, 1.6))),
        ], true),
        con(['content_width' => 'full', 'width' => sz(42, '%'), 'width_tablet' => sz(100, '%'), 'flex_direction' => 'column', 'flex_gap' => gap(18), 'padding' => box(0, 0, 0, 0)], [
            w('countdown', array_merge([
                'countdown_type' => 'due_date', 'due_date' => date('Y-m-d H:i', strtotime('+7 days')),
                'show_days' => 'yes', 'show_hours' => 'yes', 'show_minutes' => 'yes', 'show_seconds' => 'yes', 'show_labels' => 'yes',
                'custom_labels' => 'yes', 'label_days' => 'Days', 'label_hours' => 'Hours', 'label_minutes' => 'Min', 'label_seconds' => 'Sec',
                'box_background_color' => 'rgba(255,255,255,0.04)', 'box_spacing' => sz(10), 'box_padding' => box(14, 6, 14, 6), 'box_border_radius' => box(4, 4, 4, 4),
                'digits_color' => '#FFFFFF', 'label_color' => GOLD, '_css_classes' => 'btq-countdown',
                'expire_actions' => ['message'], 'message_after_expire' => 'The winter drop deal has ended — check out our sale!',
            ], typo('digits_typography', 34, '700'), typo('label_typography', 11, '500', 2, 'uppercase'))),
            con(['content_width' => 'full', 'flex_direction' => 'row', 'flex_wrap' => 'wrap', 'flex_gap' => gap(12), 'padding' => box(0, 0, 0, 0)], [
                button('Shop the jackets', get_term_link($winter), 'gold'),
                button('Full sale', $page_url('sale'), 'ghost', ['selected_icon' => ico('')]),
            ], true),
        ], true),
    ], true),
    w('woocommerce-products', [
        'query_post_type' => 'product', 'query_include' => ['terms'], 'query_include_term_ids' => [(int)$winter->term_taxonomy_id], 'query_orderby' => 'date', 'query_order' => 'desc',
        'columns' => '4', 'columns_tablet' => '2', 'columns_mobile' => '2', 'rows' => 1, 'paginate' => '', 'column_gap' => sz(20), 'column_gap_mobile' => sz(10), 'row_gap' => sz(20),
    ]),
]);
// 4. New in
$home[] = con(['content_width' => 'boxed', 'flex_direction' => 'column', 'flex_gap' => gap(28), 'padding' => box(90, 20, 40, 20), 'padding_mobile' => box(56, 16, 24, 16)], [
    con(['content_width' => 'full', 'flex_direction' => 'row', 'flex_justify_content' => 'space-between', 'flex_align_items' => 'flex-end', 'padding' => box(0, 0, 0, 0)], [
        con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(6), 'padding' => box(0, 0, 0, 0), 'width' => sz(70, '%')], [$eyebrow('Just landed'), $section_title('New in')], true),
        w('button', array_merge(['text' => 'View all', 'link' => lnk($shop_url), 'background_color' => 'rgba(0,0,0,0)', 'button_text_color' => INK, 'hover_color' => GOLD, 'text_padding' => box(6, 0, 6, 0)], typo('typography', 13, '600', 2, 'uppercase'))),
    ], true),
    w('woocommerce-products', [
        'query_post_type' => 'product', 'query_orderby' => 'date', 'query_order' => 'desc', 'query_exclude' => ['terms'], 'query_exclude_term_ids' => [$tt('jackets')],
        'columns' => '4', 'columns_tablet' => '3', 'columns_mobile' => '2', 'rows' => 2, 'paginate' => '', 'column_gap' => sz(24), 'column_gap_mobile' => sz(12), 'row_gap' => sz(40),
    ]),
    button('Show more', $shop_url, 'outline', ['align' => 'center', 'selected_icon' => ico('')]),
]);
// 5. Category tiles
$tile = fn($slug, $label) => con([
    'content_width' => 'full', 'flex_direction' => 'column', 'flex_justify_content' => 'flex-end', 'min_height' => sz(380), 'min_height_mobile' => sz(230), 'padding' => box(22, 22, 22, 22), 'padding_mobile' => box(12, 12, 12, 12),
    'background_background' => 'classic', 'background_color' => SOFT, 'background_image' => img($cat_image($slug)), 'background_position' => 'center center', 'background_size' => 'cover',
    'background_overlay_background' => 'gradient', 'background_overlay_color' => 'rgba(0,0,0,0)', 'background_overlay_color_b' => 'rgba(0,0,0,0.12)', 'background_overlay_gradient_angle' => sz(180, 'deg'),
    'link' => lnk($cat_url($slug)), 'html_tag' => 'a', '_css_classes' => 'btq-tile',
], [
    w('heading', array_merge(['title' => $label, 'header_size' => 'h3', 'title_color' => INK, 'typography_font_size_mobile' => sz(15)], typo('typography', 20, '500', 0.5))),
], true);
$home[] = con(['content_width' => 'boxed', 'flex_direction' => 'column', 'flex_gap' => gap(28), 'padding' => box(60, 20, 60, 20), 'padding_mobile' => box(40, 16, 40, 16)], [
    con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(6), 'padding' => box(0, 0, 0, 0)], [$eyebrow('Categories', GOLD, 'center'), $section_title('Shop by category', 'center')], true),
    con(['content_width' => 'full', 'container_type' => 'grid', 'grid_columns_grid' => sz(3, 'fr'), 'grid_columns_grid_tablet' => sz(3, 'fr'), 'grid_columns_grid_mobile' => sz(2, 'fr'), 'grid_rows_grid' => sz(2, 'fr'),
         'grid_gaps' => gap(16), 'grid_gaps_mobile' => gap(10), 'padding' => box(0, 0, 0, 0)], [
        $tile('shoes', 'Shoes'), $tile('t-shirts', 'T-Shirts'), $tile('jackets', 'Jackets'), $tile('pants', 'Pants'), $tile('bags', 'Bags'), $tile('accessories', 'Accessories'),
    ], true),
]);
// 6. Trending lookbook
$home[] = con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(28), 'padding' => box(70, 0, 70, 0), 'background_background' => 'classic', 'background_color' => SOFT, '_css_classes' => 'btq-pattern-light'], [
    con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(6), 'padding' => box(0, 20, 0, 20)], [$section_title('Trending', 'center'), $eyebrow('The latest looks & outfits', '#777', 'center')], true),
    w('gallery', [
        'gallery_type' => 'single', 'gallery' => array_map(fn($id) => ['id' => $id, 'url' => wp_get_attachment_url($id)], $looks),
        'gallery_layout' => 'grid', 'columns' => 6, 'columns_tablet' => 3, 'columns_mobile' => 2, 'gap' => sz(6), 'aspect_ratio' => '1:1', 'link_to' => 'none',
        'image_hover_animation' => 'grow', 'thumbnail_image_size' => 'woocommerce_thumbnail', 'overlay_background' => '',
    ]),
]);
// 7. Reviews
$reviews = [
    ['Dripbylmx exceeded my expectations! The quality of the clothing is perfect and the customer service is fast.', 'Boris T.'],
    ['I recently ordered 3 shirts from dripbylmx.com and I am really impressed with the quality.', 'Nassim L.'],
    ['Very good service and super fast delivery. I had a question about my order and got an answer right away. The glasses are perfect.', 'Lisa G.'],
    ['You find the best designer clothing here! The price/quality ratio is almost impossible to beat. The clothes I ordered are also very good.', 'Martin O.'],
];
$home[] = con(['content_width' => 'boxed', 'flex_direction' => 'column', 'flex_gap' => gap(30), 'padding' => box(80, 20, 80, 20), 'padding_mobile' => box(50, 16, 50, 16)], [
    con(['content_width' => 'full', 'flex_direction' => 'column', 'flex_gap' => gap(6), 'padding' => box(0, 0, 0, 0)], [$eyebrow('Reviews', GOLD, 'center'), $section_title('What customers say about us', 'center')], true),
    w('reviews', array_merge([
        'slides' => array_map(fn($r) => ['_id' => eid(), 'content' => $r[0], 'name' => $r[1], 'title' => 'Verified buyer', 'rating' => 5, 'image' => ['url' => '', 'id' => ''], 'selected_social_icon' => ico(''), 'link' => lnk('')], $reviews),
        'slides_per_view' => '3', 'slides_per_view_tablet' => '2', 'slides_per_view_mobile' => '1', 'slides_to_scroll' => '1', 'autoplay' => 'yes', 'autoplay_speed' => 5000, 'loop' => 'yes',
        'pagination' => 'bullets', 'show_arrows' => 'yes', 'space_between' => sz(20), 'arrows_color' => INK, 'pagination_color' => GOLD,
        'slide_background_color' => SOFT, 'slide_border_size' => box(0, 0, 0, 0), 'slide_padding' => box(30, 30, 30, 30), 'header_background_color' => SOFT,
        'star_style' => 'star_unicode', 'stars_color' => GOLD, 'stars_unmarked_color' => '#DDD', 'name_color' => INK, 'title_color' => '#888', 'content_color' => '#3A3A3A',
        'show_separator' => '',
    ], typo('content_typography', 16, '400', null, null, 1.6), typo('name_typography', 16, '600'), typo('title_typography', 12, '400', 1, 'uppercase'))),
]);
el_page('home', 'Home', $home, 'home');
echo "home ok\n";

/* ---------------- SALE PAGE ---------------- */
$page_hero = fn($eyebrowTxt, $title, $sub = '') => con(['content_width' => 'boxed', 'flex_direction' => 'column', 'flex_align_items' => 'center', 'flex_gap' => gap(12),
    'background_background' => 'gradient', 'background_color' => '#1C1712', 'background_color_b' => '#050505', 'background_gradient_type' => 'radial',
    'padding' => box(80, 20, 70, 20), 'padding_mobile' => box(50, 16, 44, 16), '_css_classes' => 'btq-pattern-dark'], array_filter([
    $eyebrow($eyebrowTxt, GOLD, 'center'),
    w('heading', array_merge(['title' => $title, 'header_size' => 'h1', 'title_color' => '#FFFFFF', 'align' => 'center', 'typography_font_size_mobile' => sz(32)], typo('typography', 52, '700', 1, 'uppercase', 1.1))),
    $sub ? w('text-editor', array_merge(['editor' => "<p>$sub</p>", 'text_color' => '#BDB6AA', 'align' => 'center'], typo('typography', 17, '400'))) : null,
]));
el_page('sale', 'Sale', [
    $page_hero('Limited time', 'Sale', 'Up to 50% off selected designer pieces. Use code <strong style="color:#E8CD8E">WINTER15</strong> for an extra 15%.'),
    con(['content_width' => 'boxed', 'flex_direction' => 'column', 'padding' => box(60, 20, 80, 20)], [
        w('woocommerce-products', ['query_post_type' => 'sale', 'query_orderby' => 'date', 'columns' => '4', 'columns_tablet' => '3', 'columns_mobile' => '2', 'rows' => 6, 'paginate' => '',
            'column_gap' => sz(24), 'column_gap_mobile' => sz(12), 'row_gap' => sz(40)]),
    ]),
], 'sale');

/* ---------------- INFO PAGES ---------------- */
$content_section = fn($children) => con(['content_width' => 'boxed', 'boxed_width' => sz(900), 'flex_direction' => 'column', 'flex_gap' => gap(18), 'padding' => box(60, 20, 90, 20)], $children);
$accordion = fn($items) => w('accordion', array_merge([
    'tabs' => array_map(fn($q) => ['_id' => eid(), 'tab_title' => $q[0], 'tab_content' => '<p>' . $q[1] . '</p>'], $items),
    'selected_icon' => ico('fas fa-plus'), 'selected_active_icon' => ico('fas fa-minus'), 'icon_align' => 'right',
    'border_color' => '#EAEAEA', 'title_color' => INK, 'tab_active_color' => GOLD, 'icon_color' => INK, 'icon_active_color' => GOLD, 'content_color' => '#555',
    'title_padding' => box(20, 20, 20, 20), 'content_padding' => box(0, 20, 20, 20),
], typo('title_typography', 17, '500'), typo('content_typography', 15, '400', null, null, 1.7)));

el_page('faq', 'FAQ', [
    $page_hero('Help center', 'Frequently asked questions'),
    $content_section([
        $section_title('Orders & shipping'),
        $accordion([
            ['When will my order arrive?', 'Items in stock that are ordered before 16:00 on a working day are shipped the same day and usually delivered the next day.'],
            ['How much does shipping cost?', 'Shipping within the Netherlands is free for orders over €100. Below that we charge a small shipping fee shown at checkout.'],
            ['Can I track my order?', 'Yes — as soon as your order ships you receive an email with a track & trace link.'],
        ]),
        $section_title('Returns & payment'),
        $accordion([
            ['Can I exchange my item?', 'Yes, you can exchange your item within 3 days of receiving it. Items must be unworn and in their original packaging.'],
            ['Which payment methods do you accept?', 'We accept iDEAL, Visa, Mastercard, PayPal and Bancontact.'],
            ['Is paying on your website safe?', 'Absolutely. All payments are processed through an encrypted, certified payment provider.'],
        ]),
    ]),
], 'faq');

$prose = fn($html) => w('text-editor', array_merge(['editor' => $html, 'text_color' => '#3A3A3A'], typo('typography', 16, '400', null, null, 1.8)));
el_page('shipping', 'Shipping & Delivery', [
    $page_hero('Customer service', 'Shipping & Delivery'),
    $content_section([$prose('<h3>Fast delivery</h3><p>Order before 16:00 on a working day and we ship your order the same day. Most orders in the Netherlands arrive the next working day; Belgium and Germany usually take 1–3 working days.</p><h3>Shipping costs</h3><ul><li>Netherlands: free over €100, otherwise €4,95</li><li>Belgium &amp; Germany: €7,95</li><li>Rest of Europe: €12,95</li></ul><h3>Track &amp; trace</h3><p>You receive a tracking link by email as soon as your parcel leaves our warehouse.</p>')]),
], 'shipping-delivery');
el_page('returns', 'Returns & Exchanges', [
    $page_hero('Customer service', 'Returns & Exchanges'),
    $content_section([$prose('<h3>Exchange guarantee</h3><p>Wrong size? No problem. You can exchange your item within 3 days after receiving it.</p><h3>Conditions</h3><ul><li>The item is unworn, unwashed and in its original packaging</li><li>All tags are still attached</li><li>You contact us via WhatsApp or email before sending the item back</li></ul><h3>How it works</h3><ol><li>Contact us with your order number</li><li>Send the item back to the address we provide</li><li>We ship your new size as soon as we receive the return</li></ol>')]),
], 'returns-exchanges');
el_page('terms', 'Terms & Conditions', [
    $page_hero('Legal', 'Terms & Conditions'),
    $content_section([$prose('<p>These are placeholder terms and conditions. Replace this text with your own legal terms before launching your store.</p>')]),
], 'terms-conditions');
el_page('about', 'About Us', [
    $page_hero('Our story', 'About LMX Boutique', 'Premium fashion &amp; luxury essentials for people with style.'),
    con(['content_width' => 'boxed', 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_gap' => gap(50), 'flex_align_items' => 'center', 'padding' => box(80, 20, 80, 20)], [
        con(['content_width' => 'full', 'width' => sz(50, '%'), 'width_mobile' => sz(100, '%'), 'padding' => box(0, 0, 0, 0)], [w('image', ['image' => img($about_img), 'image_size' => 'large'])], true),
        con(['content_width' => 'full', 'width' => sz(50, '%'), 'width_mobile' => sz(100, '%'), 'flex_direction' => 'column', 'flex_gap' => gap(16), 'padding' => box(0, 0, 0, 0)], [
            $eyebrow('Wear confidence'), $section_title('Carefully selected, personally delivered'),
            $prose('<p>LMX Boutique started with one simple idea: premium fashion should feel personal. We hand-pick every piece in our collection and keep most items in stock, so your order ships within 24 hours.</p><p>Have a question about sizes or styling? Our team is available on WhatsApp every day.</p>'),
            button('Discover the collection', $shop_url, 'dark'),
        ], true),
    ]),
], 'about-us');
el_page('contact', 'Contact', [
    $page_hero('We\'re here to help', 'Contact'),
    con(['content_width' => 'boxed', 'boxed_width' => sz(1100), 'flex_direction' => 'row', 'flex_direction_mobile' => 'column', 'flex_gap' => gap(50), 'padding' => box(70, 20, 90, 20)], [
        con(['content_width' => 'full', 'width' => sz(40, '%'), 'width_mobile' => sz(100, '%'), 'flex_direction' => 'column', 'flex_gap' => gap(18), 'padding' => box(0, 0, 0, 0)], [
            $section_title('Get in touch'),
            $prose('<p>The fastest way to reach us is WhatsApp. We usually reply within an hour during opening hours.</p>'),
            w('icon-list', array_merge(['icon_list' => [
                ['_id' => eid(), 'text' => 'WhatsApp: ' . $PHONE, 'selected_icon' => ico('fab fa-whatsapp', 'fa-brands'), 'link' => lnk($WHATSAPP)],
                ['_id' => eid(), 'text' => 'Phone: ' . $PHONE, 'selected_icon' => ico('fas fa-phone-alt'), 'link' => lnk($TEL)],
                ['_id' => eid(), 'text' => $EMAIL, 'selected_icon' => ico('far fa-envelope', 'fa-regular'), 'link' => lnk('mailto:' . $EMAIL)],
                ['_id' => eid(), 'text' => 'Mon – Sat: 09:00 – 21:00', 'selected_icon' => ico('far fa-clock', 'fa-regular')],
            ], 'icon_color' => GOLD, 'text_color' => INK, 'space_between' => sz(14), 'icon_size' => sz(18), 'text_indent' => sz(10)], typo('icon_typography', 16, '400'))),
        ], true),
        con(['content_width' => 'full', 'width' => sz(60, '%'), 'width_mobile' => sz(100, '%'), 'padding' => box(36, 36, 36, 36), 'background_background' => 'classic', 'background_color' => SOFT], [
            w('form', array_merge([
                'form_name' => 'Contact',
                'form_fields' => [
                    ['_id' => eid(), 'custom_id' => 'name', 'field_type' => 'text', 'field_label' => 'Name', 'placeholder' => 'Your name', 'required' => 'true', 'width' => '50'],
                    ['_id' => eid(), 'custom_id' => 'email', 'field_type' => 'email', 'field_label' => 'Email', 'placeholder' => 'you@example.com', 'required' => 'true', 'width' => '50'],
                    ['_id' => eid(), 'custom_id' => 'order', 'field_type' => 'text', 'field_label' => 'Order number (optional)', 'placeholder' => '#', 'width' => '100'],
                    ['_id' => eid(), 'custom_id' => 'message', 'field_type' => 'textarea', 'field_label' => 'Message', 'placeholder' => 'How can we help?', 'required' => 'true', 'width' => '100', 'rows' => 5],
                ],
                'button_text' => 'Send message', 'button_width' => '100', 'submit_actions' => ['email', 'save-to-database'],
                'email_to' => get_option('admin_email'), 'email_subject' => 'New message from the LMX Boutique contact form', 'email_content' => '[all-fields]',
                'success_message' => 'Thank you! We will get back to you as soon as possible.', 'custom_messages' => 'yes',
                'label_color' => INK, 'field_background_color' => '#FFFFFF', 'field_border_color' => '#E2DED8', 'field_border_radius' => box(2, 2, 2, 2),
                'button_background_color' => INK, 'button_text_color' => '#FFFFFF', 'button_background_hover_color' => GOLD, 'button_hover_color' => INK, 'row_gap' => sz(16),
            ], typo('button_typography', 14, '600', 3, 'uppercase'), typo('label_typography', 14, '500'), typo('field_typography', 15, '400'))),
        ], true),
    ]),
], 'contact');

/* ---------------- WOO PAGES (cart / checkout / account) ---------------- */
$woo_page = function ($wc_key, $title, $widget, $settings = []) use ($page_hero) {
    $id = wc_get_page_id($wc_key);
    bstate("page_$wc_key", $id);
    $slug = get_post_field('post_name', $id);
    el_page($wc_key, $title, [
        con(['content_width' => 'boxed', 'flex_direction' => 'column', 'flex_align_items' => 'center', 'background_background' => 'classic', 'background_color' => SOFT, 'padding' => box(50, 20, 44, 20), '_css_classes' => 'btq-pattern-light'], [
            w('heading', array_merge(['title' => $title, 'header_size' => 'h1', 'title_color' => INK, 'align' => 'center', 'typography_font_size_mobile' => sz(28)], typo('typography', 40, '700', 2, 'uppercase'))),
        ]),
        con(['content_width' => 'boxed', 'flex_direction' => 'column', 'padding' => box(50, 20, 90, 20)], [w($widget, $settings)]),
    ], $slug);
};
$btn = ['checkout_button_background_color' => INK, 'checkout_button_text_color' => '#FFFFFF', 'checkout_button_hover_background_color' => GOLD, 'checkout_button_hover_text_color' => INK,
        'purchase_button_background_color' => INK, 'purchase_button_normal_text_color' => '#FFFFFF', 'purchase_button_hover_background_color' => GOLD, 'purchase_button_hover_text_color' => INK];
$woo_page('cart', 'Shopping bag', 'woocommerce-cart', $btn + ['layout' => 'two-column', 'sticky_right_column' => 'yes']);
$woo_page('checkout', 'Checkout', 'woocommerce-checkout-page', $btn + ['checkout_layout' => 'two-column', 'sticky_right_column' => 'yes']);
$woo_page('myaccount', 'My account', 'woocommerce-my-account', ['tabs_layout' => 'vertical']);
echo "pages ok\n";

/* ---------------- Finish ---------------- */
\Elementor\Plugin::$instance->files_manager->clear_cache();
flush_rewrite_rules(false);
echo "ALL DONE\n";
