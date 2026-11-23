<?php
/**
 * Plugin Name: LMX Store Tweaks
 * Description: Front-end performance clean-up and small shop behaviours for LMX Boutique (Elementor + WooCommerce + Astra).
 */
if (!defined('ABSPATH')) exit;

/* ---------- Performance ---------- */

// No emoji script/styles (modern browsers render emoji natively).
add_action('init', function () {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('emoji_svg_url', '__return_false');
    // Head clutter
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
});

// The site is built with Elementor and classic WooCommerce templates, so block-editor styles are not needed on the front end.
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) return;
    foreach (['wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'wc-blocks-style', 'wc-blocks-vendors-style'] as $h) {
        wp_dequeue_style($h);
        wp_deregister_style($h);
    }
}, 100);
remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');

/* ---------- Shop behaviour ---------- */

// 24 products per shop/category page.
add_filter('loop_shop_per_page', fn() => 24, 30);

// Site search returns products (uses the Elementor shop archive template).
add_action('pre_get_posts', function ($q) {
    if (!is_admin() && $q->is_main_query() && $q->is_search() && empty($q->get('post_type'))) {
        $q->set('post_type', 'product');
    }
});

// Sale badges show the discount percentage, e.g. "-18%", like the old site.
function lmx_discount_percent($product) {
    if (!$product instanceof WC_Product || !$product->is_on_sale()) return 0;
    $pairs = [];
    if ($product->is_type('variable')) {
        // get_variation_prices() is cached by WooCommerce, so this stays cheap on large grids.
        $prices = $product->get_variation_prices();
        foreach ($prices['regular_price'] as $vid => $reg) $pairs[] = [(float)$reg, (float)($prices['sale_price'][$vid] ?? 0)];
    } else {
        $pairs[] = [(float)$product->get_regular_price(), (float)$product->get_sale_price()];
    }
    $max = 0;
    foreach ($pairs as [$reg, $sale]) if ($reg > 0 && $sale > 0 && $sale < $reg) $max = max($max, (int)round(100 - $sale / $reg * 100));
    return $max;
}
function lmx_badge_text($html, $product) {
    $pct = lmx_discount_percent($product);
    return $pct ? preg_replace('/>\s*Sale!?\s*</i', '>-' . $pct . '%<', $html) : $html;
}
// Runs after Astra (priority 10), which builds the badge markup on single product pages.
add_filter('woocommerce_sale_flash', fn($html, $post, $product) => $html === '' ? '' : lmx_badge_text($html, $product), 20, 3);
// Astra's product-card badge on shop grids.
add_filter('astra_addon_shop_cards_buttons_html', fn($html, $product) => lmx_badge_text($html, $product), 20, 2);

/* ---------- Transparent header over the homepage hero (desktop) ---------- */
// Marks the homepage so kit.css can lay the header over the hero photo.
add_filter('body_class', function ($classes) {
    if (is_front_page()) $classes[] = 'btq-transparent-header';
    return $classes;
});
// Adds "btq-scrolled" to <body> once the visitor scrolls, so the header turns solid white.
add_action('wp_footer', function () {
    if (!is_front_page()) return;
    echo "<script>(function(){var b=document.body,s=false;function u(){var n=window.scrollY>40;if(n!==s){s=n;b.classList.toggle('btq-scrolled',n);}}u();window.addEventListener('scroll',function(){window.requestAnimationFrame(u);},{passive:true});})();</script>\n";
}, 99);
