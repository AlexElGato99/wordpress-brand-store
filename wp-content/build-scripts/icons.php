<?php
/**
 * Small line-icon set used instead of emojis (24x24, stroke = currentColor).
 * lmx_icon('truck') returns inline SVG; lmx_icon_css() returns CSS mask classes (.lmx-ic-*) for use in plain HTML/content.
 */
function lmx_icon_paths() {
    return [
        'truck'   => '<path d="M2.5 6.5h11v9.5h-11z"/><path d="M13.5 10h4.2l3.3 3.4V16h-7.5"/><circle cx="7" cy="17.6" r="1.9"/><circle cx="17.2" cy="17.6" r="1.9"/>',
        'package' => '<path d="M3.5 7.4 12 3l8.5 4.4v9.2L12 21l-8.5-4.4z"/><path d="M3.5 7.4 12 11.8l8.5-4.4M12 11.8V21M7.7 5.2l8.5 4.4"/>',
        'tag'     => '<path d="M3 11.6V3.5h8.1l9.4 9.4-8.1 8.1z"/><circle cx="7.6" cy="8" r="1.6"/>',
        'snow'    => '<path d="M12 2.5v19M3.8 7.2l16.4 9.6M3.8 16.8l16.4-9.6"/><path d="m9.5 4.5 2.5 2 2.5-2M9.5 19.5l2.5-2 2.5 2M4.4 10.3l3.1-.5-1.1-3M19.6 13.7l-3.1.5 1.1 3M6.4 17.2l1.1-3-3.1-.5M17.6 6.8l-1.1 3 3.1.5"/>',
        'alert'   => '<path d="M12 3.2 2.3 20h19.4z"/><path d="M12 9.5v4.8M12 17.2v.3"/>',
        'check'   => '<path d="m4.5 12.5 4.8 4.8L19.5 7"/>',
        'arrow'   => '<path d="M4 12h15.5M13.5 6l6 6-6 6"/>',
    ];
}
function lmx_icon($name, $size = 18, $class = '') {
    $p = lmx_icon_paths()[$name] ?? '';
    return '<svg class="lmx-svg ' . $class . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}
function lmx_icon_css() {
    $css = "/* Line icons (replace emojis) */\n.lmx-ic { display: inline-block; width: 1.05em; height: 1.05em; vertical-align: -0.17em; margin-right: .45em; background-color: currentColor; -webkit-mask: var(--i) center / contain no-repeat; mask: var(--i) center / contain no-repeat; flex: 0 0 auto; }\n";
    foreach (lmx_icon_paths() as $n => $p) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
        $css .= ".lmx-ic-$n { --i: url(\"data:image/svg+xml," . rawurlencode($svg) . "\"); }\n";
    }
    return $css;
}

/* ---------- Brand SVG artwork ---------- */
/** Seamless art-deco lattice tile (96x96): diamonds, inner diamonds, corner rings and a four-point star. */
function lmx_pattern_svg($color = '#C9A45C', $opacity = 1) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">'
        . '<g fill="none" stroke="' . $color . '" stroke-opacity="' . $opacity . '" stroke-width=".7">'
        . '<path d="M48 0 96 48 48 96 0 48Z"/>'
        . '<path d="M48 16 80 48 48 80 16 48Z" stroke-opacity="' . round($opacity * .55, 3) . '"/>'
        . '<circle cx="0" cy="0" r="7"/><circle cx="96" cy="0" r="7"/><circle cx="0" cy="96" r="7"/><circle cx="96" cy="96" r="7"/>'
        . '<path d="M0 48h6M90 48h6M48 0v6M48 90v6" stroke-opacity="' . round($opacity * .7, 3) . '"/>'
        . '</g>'
        . '<path fill="' . $color . '" fill-opacity="' . $opacity . '" d="M48 42.5l1.3 4.2 4.2 1.3-4.2 1.3L48 53.5l-1.3-4.2-4.2-1.3 4.2-1.3z"/>'
        . '</svg>';
}
/** Outlined "LMX" monogram with a double rule, drawn as paths (no font needed). */
function lmx_monogram_svg($color = '#C9A45C', $opacity = 1) {
    return '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="260" viewBox="0 0 640 260">'
        . '<g fill="none" stroke="' . $color . '" stroke-opacity="' . $opacity . '" stroke-linecap="square" stroke-linejoin="miter">'
        . '<g stroke-width="2.2"><path d="M40 30v170h120"/><path d="M215 200V30l85 120 85-120v170"/><path d="M440 30l160 170M600 30L440 200"/></g>'
        . '<g stroke-width=".8"><path d="M54 30v156h106"/><path d="M229 200V58l71 100 71-100v142"/><path d="M462 30l138 146M578 30L440 176"/></g>'
        . '<path stroke-width=".8" d="M40 228h560M40 236h560"/>'
        . '</g></svg>';
}
function lmx_svg_url($svg) { return 'url("data:image/svg+xml,' . rawurlencode($svg) . '")'; }
/** CSS custom properties with the artwork, appended to the kit CSS by 04-polish.php. */
function lmx_artwork_css() {
    return ":root {\n"
        . "  --lmx-pattern-dark: " . lmx_svg_url(lmx_pattern_svg('#C9A45C', .16)) . ";\n"
        . "  --lmx-pattern-light: " . lmx_svg_url(lmx_pattern_svg('#B8893D', .14)) . ";\n"
        . "  --lmx-monogram: " . lmx_svg_url(lmx_monogram_svg('#C9A45C', .09)) . ";\n"
        . "}\n";
}
