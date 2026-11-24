<?php
/** Section banner presentation for the Elementor pages. */
if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
    ?>
    <style id="lmx-generated-banner-styles">
    .btq-generated-banner { position:relative; isolation:isolate; overflow:hidden; background-position:center; background-size:cover; min-height:230px; }
    .btq-generated-banner::before { content:""; position:absolute; inset:0; z-index:0; pointer-events:none; background:linear-gradient(90deg,rgba(12,10,9,.72),rgba(12,10,9,.53),rgba(12,10,9,.68)); }
    .btq-generated-banner::after { display:none!important; }
    .btq-generated-banner > .e-con-inner,.btq-generated-banner > .elementor-widget { position:relative; z-index:1; }
    .btq-generated-banner .elementor-heading-title,.btq-generated-banner .elementor-widget-text-editor,.btq-generated-banner .woocommerce-breadcrumb,.btq-generated-banner .woocommerce-breadcrumb a { color:#fff!important; text-shadow:0 1px 12px rgba(0,0,0,.55); }
    .btq-generated-banner .btq-page-sub { color:rgba(255,255,255,.9)!important; }
    .lmx-privacy-banner { min-height:230px; display:flex; align-items:center; justify-content:center; position:relative; background-position:center; background-size:cover; color:#fff; text-align:center; }
    .lmx-privacy-banner:before { content:""; position:absolute; inset:0; background:rgba(12,10,9,.6); }
    .lmx-privacy-banner h1 { position:relative; color:#fff; margin:0; padding:32px; }
    @media(max-width:767px) { .btq-generated-banner,.lmx-privacy-banner { min-height:180px; } }
    </style>
    <?php
});

add_filter('the_content', function ($content) {
    if (!is_page(3) || !is_main_query() || !in_the_loop()) return $content;
    $state = get_option('boutique_build_state', []);
    $url = wp_get_attachment_image_url((int)($state['generated_banner_legal'] ?? 0), 'full');
    if (!$url) return $content;
    return '<section class="lmx-privacy-banner" style="background-image:url(' . esc_url($url) . ')"><h1>' . esc_html(get_the_title()) . '</h1></section>' . $content;
});
