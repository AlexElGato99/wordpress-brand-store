<?php
/**
 * Plugin Name: LMX Store Sections
 * Description: Shop banners, Trending video reels and swipeable product rails (sections from the old LMX site, in the new style).
 * Media IDs come from the build state written by wp-content/build-scripts/08-import-site-media.php.
 */
if (!defined('ABSPATH')) exit;

function lmx_state($key) { $s = get_option('boutique_build_state', []); return $s[$key] ?? null; }

/* ---------- [lmx_shop_banner] : media banner at the top of shop / category pages ---------- */
add_shortcode('lmx_shop_banner', function () {
    $term = is_product_category() ? get_queried_object() : null;
    $slug = $term ? $term->slug : '';
    // Same banners as the old site: Men + Women have a video, Sale its own photo, everything else the shared store photo.
    $key = in_array($slug, ['heren', 'dames', 'sale'], true) ? $slug : 'default';
    $copy = [
        'heren'   => ['Discover the latest designer fashion', 'Men’s Collection'],
        'dames'   => ['Discover the best designer brands in our collection', 'Experience the quality of Dripbylmx Fashion'],
        'sale'    => ['Exclusive deals on designer items', 'Sale'],
        'default' => ['Discover the best designer brands in our collection', 'Experience the quality of Dripbylmx Fashion'],
    ][$key];
    $title = $term ? $term->name : (is_search() ? sprintf('Search: %s', get_search_query()) : 'Shop');
    $video = (int)lmx_state("shop_banner_{$key}_video");
    $poster = (int)lmx_state("shop_banner_{$key}_poster");
    $poster_url = $poster ? wp_get_attachment_image_url($poster, 'full') : '';
    $media = $video
        ? '<video class="lmx-banner-media" src="' . esc_url(wp_get_attachment_url($video)) . '" poster="' . esc_url($poster_url) . '" autoplay muted loop playsinline preload="metadata" aria-hidden="true"></video>'
        : ($poster ? wp_get_attachment_image($poster, 'full', false, ['class' => 'lmx-banner-media', 'alt' => '', 'fetchpriority' => 'high', 'loading' => 'eager']) : '');
    $out  = '<section class="lmx-shop-banner lmx-shop-banner--' . esc_attr($key) . '">' . $media . '<div class="lmx-shop-banner__inner">';
    $out .= '<p class="lmx-shop-banner__eyebrow">' . esc_html($copy[1]) . '</p>';
    $out .= '<h1 class="lmx-shop-banner__title">' . esc_html($title) . '</h1>';
    $out .= '<p class="lmx-shop-banner__sub">' . esc_html($copy[0]) . '</p>';
    return $out . '</div></section>';
});

/* ---------- [lmx_trending_reels] : the old site's 7 outfit videos as a reels rail ---------- */
add_shortcode('lmx_trending_reels', function () {
    $ids = (array)lmx_state('trending_videos');
    if (!$ids) return '';
    $shop = get_permalink(wc_get_page_id('shop'));
    $out = '<div class="lmx-rail lmx-reels" data-lmx-rail><div class="lmx-rail__track">';
    foreach ($ids as $i => $id) {
        $out .= '<a class="lmx-reel" href="' . esc_url($shop) . '" aria-label="Trending outfit ' . ($i + 1) . '">'
             . '<video data-src="' . esc_url(wp_get_attachment_url($id)) . '" muted loop playsinline preload="none" aria-hidden="true"></video>'
             . '<span class="lmx-reel__shade"></span></a>';
    }
    return $out . '</div><button class="lmx-rail__btn lmx-rail__btn--prev" type="button" aria-label="Previous">' . lmx_rail_arrow(true) . '</button>'
        . '<button class="lmx-rail__btn lmx-rail__btn--next" type="button" aria-label="Next">' . lmx_rail_arrow(false) . '</button></div>';
});
function lmx_rail_arrow($left) {
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($left ? '<path d="M19.5 12H4M10 6l-6 6 6 6"/>' : '<path d="M4.5 12H20M14 6l6 6-6 6"/>') . '</svg>';
}

/* ---------- Front-end behaviour: rails (arrows + swipe) and videos that only play when visible ---------- */
add_action('wp_footer', function () {
    ?>
<script>
(function () {
  var arrow = function (left) { return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (left ? '<path d="M19.5 12H4M10 6l-6 6 6 6"/>' : '<path d="M4.5 12H20M14 6l6 6-6 6"/>') + '</svg>'; };
  // Product rails: Elementor product widgets with the .btq-rail class become one swipeable row with arrows.
  document.querySelectorAll('.btq-rail').forEach(function (w) {
    var list = w.querySelector('ul.products'); if (!list || w.dataset.lmxRail) return;
    w.dataset.lmxRail = '1'; w.classList.add('lmx-rail'); list.classList.add('lmx-rail__track');
    ['prev', 'next'].forEach(function (d) {
      var b = document.createElement('button'); b.type = 'button'; b.className = 'lmx-rail__btn lmx-rail__btn--' + d;
      b.setAttribute('aria-label', d === 'prev' ? 'Previous' : 'Next'); b.innerHTML = arrow(d === 'prev'); w.appendChild(b);
    });
  });
  document.querySelectorAll('.lmx-rail').forEach(function (rail) {
    var track = rail.querySelector('.lmx-rail__track'); if (!track) return;
    var prev = rail.querySelector('.lmx-rail__btn--prev'), next = rail.querySelector('.lmx-rail__btn--next');
    var step = function () { var item = track.children[0]; return item ? item.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || 0) : 300; };
    var update = function () {
      var max = track.scrollWidth - track.clientWidth - 2;
      if (prev) prev.disabled = track.scrollLeft <= 2; if (next) next.disabled = track.scrollLeft >= max;
      rail.classList.toggle('is-static', max <= 0);
    };
    if (prev) prev.addEventListener('click', function () { track.scrollBy({ left: -step() * 2, behavior: 'smooth' }); });
    if (next) next.addEventListener('click', function () { track.scrollBy({ left: step() * 2, behavior: 'smooth' }); });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
    window.addEventListener('resize', update); update();
  });
  // Reels: load and play only while on screen (saves ~36 MB when the section is never reached).
  var vids = document.querySelectorAll('.lmx-reel video');
  if ('IntersectionObserver' in window && vids.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        var v = e.target;
        if (e.isIntersecting) { if (!v.src) { v.src = v.dataset.src; } var p = v.play(); if (p && p.catch) p.catch(function () {}); }
        else if (!v.paused) v.pause();
      });
    }, { rootMargin: '200px 0px', threshold: 0.2 });
    vids.forEach(function (v) { io.observe(v); });
  }
  // Banner videos respect reduced-motion settings.
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.lmx-banner-media, .lmx-reel video').forEach(function (v) { if (v.pause) { v.removeAttribute('autoplay'); v.pause(); } });
  }
})();
</script>
    <?php
}, 98);
