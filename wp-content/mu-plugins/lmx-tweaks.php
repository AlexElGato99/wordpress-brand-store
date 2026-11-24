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
add_filter('loop_shop_per_page', fn() => 16, 30);   // 4 x 4, like the old site

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

/* ---------- Shop UX: brand names, hover image, subcategory chips, size buttons ---------- */

// Brand = child category of "Merken" (e.g. Louis Vuitton, Dior, Hermès).
function lmx_product_brand($product_id) {
    static $parent = null;
    if ($parent === null) { $t = get_term_by('slug', 'merken', 'product_cat'); $parent = $t ? (int)$t->term_id : 0; }
    if (!$parent) return null;
    foreach (wp_get_post_terms($product_id, 'product_cat') as $term) if ((int)$term->parent === $parent) return $term;
    return null;
}

// Brand line above the product name on every product card.
add_action('astra_woo_shop_title_before', function () {
    $b = lmx_product_brand(get_the_ID());
    if ($b) echo '<span class="lmx-card-brand">' . esc_html($b->name) . '</span>';
});

// Second photo that fades in on hover (desktop), printed inside Astra's image wrapper.
add_action('woocommerce_after_shop_loop_item', function () {
    global $product;
    if (!$product) return;
    $ids = $product->get_gallery_image_ids();
    if (!$ids) return;
    echo wp_get_attachment_image($ids[0], 'woocommerce_thumbnail', false, ['class' => 'lmx-hover-img', 'loading' => 'lazy', 'alt' => '', 'aria-hidden' => 'true']);
}, 6);

// [lmx_brand] – brand link on the product page.
add_shortcode('lmx_brand', function () {
    $b = is_product() ? lmx_product_brand(get_the_ID()) : null;
    if (!$b) return '';
    return '<a class="lmx-pdp-brand" href="' . esc_url(get_term_link($b)) . '">' . esc_html($b->name) . '</a>';
});

// [lmx_subcats] – subcategory chips on shop/category pages.
add_shortcode('lmx_subcats', function () {
    if (is_product_category()) {
        $current = get_queried_object();
        $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => $current->term_id, 'hide_empty' => true, 'orderby' => 'name']);
        if (!$terms && $current->parent) $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => $current->parent, 'hide_empty' => true, 'orderby' => 'name']);
    } elseif (is_shop()) {
        $terms = get_terms(['taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'slug' => ['heren', 'dames', 'sale', 'voorraad', 'op-bestelling']]);
        $current = null;
    } else return '';
    if (empty($terms) || is_wp_error($terms)) return '';
    $out = '<nav class="lmx-chips" aria-label="Categories">';
    foreach ($terms as $t) {
        $active = $current && $current->term_id === $t->term_id;
        $out .= '<a class="lmx-chip' . ($active ? ' is-active' : '') . '" href="' . esc_url(get_term_link($t)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . esc_html($t->name) . '<span>' . (int)$t->count . '</span></a>';
    }
    return $out . '</nav>';
});

// Size buttons instead of dropdowns on product pages (the real <select> stays in sync for WooCommerce).
add_action('wp_footer', function () {
    if (!is_product()) return;
    ?>
<script>
(function () {
  function build(form) {
    form.querySelectorAll('.variations select').forEach(function (sel) {
      if (sel.dataset.lmxSwatches) return; sel.dataset.lmxSwatches = '1';
      var wrap = document.createElement('div'); wrap.className = 'lmx-swatches'; wrap.setAttribute('role', 'radiogroup');
      sel.parentNode.insertBefore(wrap, sel); sel.classList.add('lmx-select-hidden');
      function render() {
        wrap.innerHTML = '';
        Array.prototype.forEach.call(sel.options, function (o) {
          if (!o.value) return;
          var b = document.createElement('button'); b.type = 'button'; b.className = 'lmx-swatch'; b.textContent = o.text;
          b.setAttribute('role', 'radio'); b.setAttribute('aria-checked', sel.value === o.value ? 'true' : 'false');
          if (sel.value === o.value) b.classList.add('is-selected');
          if (o.disabled) { b.disabled = true; b.classList.add('is-disabled'); }
          b.addEventListener('click', function () {
            sel.value = sel.value === o.value ? '' : o.value;
            sel.dispatchEvent(new Event('change', { bubbles: true }));   // jQuery/WooCommerce handlers receive native events too
          });
          wrap.appendChild(b);
        });
      }
      render();
      sel.addEventListener('change', render);
      if (window.jQuery) jQuery(form).on('woocommerce_update_variation_values reset_data', function () { setTimeout(render, 0); });
    });
  }
  function init() { document.querySelectorAll('form.variations_form').forEach(build); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
  if (window.jQuery) jQuery(document).on('wc_variation_form', function (e) { build(e.target); });
})();
</script>
    <?php
}, 99);

// Friendlier heading above related products.
add_filter('woocommerce_product_related_products_heading', fn() => 'You may also like');

/* ---------- English category names on the storefront (data in the admin stays as imported) ---------- */
function lmx_cat_label_map() {
    return ['Accessoires' => 'Accessories', 'Bestsellers' => 'Bestsellers', 'Broeken' => 'Pants', 'Dames' => 'Women', 'Heren' => 'Men', 'Horloges' => 'Watches',
        'Jassen' => 'Jackets', 'Kleding' => 'Clothing', 'Merken' => 'Brands', 'Mutsen & Sjaals' => 'Beanies & Scarves', 'Op bestelling' => 'Made to Order',
        'Overhemden' => 'Shirts & Overshirts', 'Parfum' => 'Perfume', 'Petten & Mutsen' => 'Caps & Beanies', 'Polo’s' => 'Polos', 'Riemen' => 'Belts',
        'Sandalen' => 'Sandals', 'Schoenen' => 'Shoes', 'Sieraden' => 'Jewellery', 'Slippers' => 'Slides', 'Tassen' => 'Bags', 'Trainingspakken' => 'Tracksuits',
        'Truien' => 'Sweaters', 'Tussenjassen' => 'Light Jackets', 'Vesten' => 'Cardigans', 'Voorraad' => 'In Stock', 'Winterjassen' => 'Winter Jackets',
        'Zomersetjes' => 'Summer Sets', 'Zonnebrillen' => 'Sunglasses', 'Zwembroeken' => 'Swim Shorts', 'Uncategorized' => 'Other'];
}
add_filter('get_product_cat', function ($term) {
    if (is_admin() && !wp_doing_ajax()) return $term;
    if ($term instanceof WP_Term) {
        $name = html_entity_decode($term->name, ENT_QUOTES, 'UTF-8');
        $map = lmx_cat_label_map();
        if (isset($map[$name])) $term->name = esc_html($map[$name]);
        // English description (stored by build-scripts/07-category-descriptions.php)
        $en = get_term_meta($term->term_id, 'lmx_description_en', true);
        if ($en) $term->description = $en;
    }
    return $term;
});

// English attribute labels on the storefront (e.g. "Maat" -> "Size").
add_filter('woocommerce_attribute_label', function ($label) {
    if (is_admin() && !wp_doing_ajax()) return $label;
    $map = ['Maat' => 'Size', 'Kleur' => 'Colour', 'Materiaal' => 'Material', 'Lengte' => 'Length', 'Maten' => 'Sizes'];
    return $map[$label] ?? $label;
}, 20);
