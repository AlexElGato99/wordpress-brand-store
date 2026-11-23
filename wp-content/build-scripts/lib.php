<?php
/**
 * Helpers for building the Boutique store with Elementor / Elementor Pro data.
 * Run the build with: C:\xampp\php\php.exe wp-content\build-scripts\build.php
 */
if (!defined('ABSPATH')) {
    define('WP_USE_THEMES', false);
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['REQUEST_URI'] = '/boutique/';
    require dirname(__DIR__, 2) . '/wp-load.php';
}
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_set_current_user(1);

const GOLD = '#C9A45C';
const GOLD_LIGHT = '#E8CD8E';
const INK = '#0B0B0B';
const SOFT = '#F6F4F1';
const FONT = 'Jost';

/* ---------- state (keeps the build idempotent) ---------- */
function bstate($key, $val = null) {
    $s = get_option('boutique_build_state', []);
    if ($val === null) return $s[$key] ?? null;
    $s[$key] = $val;
    update_option('boutique_build_state', $s, false);
    return $val;
}

/* ---------- Elementor element builders ---------- */
function eid() { return substr(md5(uniqid('', true) . mt_rand()), 0, 7); }
function con(array $s, array $children = [], bool $inner = false) {
    $s += ['content_width' => 'boxed'];
    if (isset($s['_css_classes'])) { $s['css_classes'] = $s['_css_classes']; unset($s['_css_classes']); }
    return ['id' => eid(), 'elType' => 'container', 'isInner' => $inner, 'settings' => $s, 'elements' => $children];
}
function w(string $type, array $s) {
    return ['id' => eid(), 'elType' => 'widget', 'widgetType' => $type, 'settings' => $s, 'elements' => []];
}
function sz($v, $u = 'px') { return ['unit' => $u, 'size' => $v, 'sizes' => []]; }
function box($t, $r, $b, $l, $u = 'px') {
    return ['unit' => $u, 'top' => (string)$t, 'right' => (string)$r, 'bottom' => (string)$b, 'left' => (string)$l, 'isLinked' => false];
}
function gap($v) { return ['unit' => 'px', 'size' => $v, 'column' => (string)$v, 'row' => (string)$v, 'isLinked' => true]; }
function lnk($url) { return ['url' => $url, 'is_external' => '', 'nofollow' => '', 'custom_attributes' => '']; }
function ico($v, $lib = 'fa-solid') { return ['value' => $v, 'library' => $v === '' ? '' : $lib]; }
/** Typography group control. $p = prefix like 'typography' or 'title_typography'. */
function typo($p, $size, $weight = '400', $ls = null, $transform = null, $lh = null, $sizeM = null) {
    $o = [
        "{$p}_typography" => 'custom',
        "{$p}_font_family" => FONT,
        "{$p}_font_size" => sz($size),
        "{$p}_font_weight" => (string)$weight,
    ];
    if ($ls !== null) $o["{$p}_letter_spacing"] = sz($ls);
    if ($transform) $o["{$p}_text_transform"] = $transform;
    if ($lh !== null) $o["{$p}_line_height"] = sz($lh, 'em');
    if ($sizeM !== null) $o["{$p}_font_size_mobile"] = sz($sizeM);
    return $o;
}
function heading($text, $tag = 'h2', $size = 32, $weight = '600', $extra = []) {
    return w('heading', array_merge([
        'title' => $text, 'header_size' => $tag, 'title_color' => INK,
    ], typo('typography', $size, $weight, null, null, 1.15, $size > 30 ? round($size * 0.7) : null), $extra));
}
function text($html, $color = '#444444', $size = 16, $extra = []) {
    return w('text-editor', array_merge(['editor' => $html, 'text_color' => $color], typo('typography', $size, '400', null, null, 1.6), $extra));
}
function button($label, $url, $style = 'gold', $extra = []) {
    $styles = [
        'gold'  => ['background_color' => GOLD, 'button_text_color' => INK, 'button_background_hover_color' => GOLD_LIGHT, 'hover_color' => INK],
        'dark'  => ['background_color' => INK, 'button_text_color' => '#FFFFFF', 'button_background_hover_color' => '#2A2A2A', 'hover_color' => '#FFFFFF'],
        'ghost' => ['background_color' => 'rgba(0,0,0,0)', 'button_text_color' => '#FFFFFF', 'border_border' => 'solid', 'border_width' => box(1, 1, 1, 1), 'border_color' => 'rgba(255,255,255,0.45)', 'button_background_hover_color' => '#FFFFFF', 'hover_color' => INK],
        'outline' => ['background_color' => 'rgba(0,0,0,0)', 'button_text_color' => INK, 'border_border' => 'solid', 'border_width' => box(1, 1, 1, 1), 'border_color' => INK, 'button_background_hover_color' => INK, 'hover_color' => '#FFFFFF'],
    ];
    return w('button', array_merge([
        'text' => $label, 'link' => lnk($url),
        'selected_icon' => ico(''),   // buttons have no icons
        'border_radius' => box(2, 2, 2, 2),
        'text_padding' => box(18, 34, 18, 34),
        'text_padding_mobile' => box(15, 22, 15, 22),
    ], typo('typography', 14, '600', 2.5, 'uppercase'), $styles[$style], $extra));
}
function css_el($css) { return ['custom_css' => $css]; }

/* ---------- Saving documents ---------- */
function el_save_post($post_id, array $elements, array $page_settings = []) {
    $doc = \Elementor\Plugin::$instance->documents->get($post_id, false);
    update_post_meta($post_id, '_elementor_edit_mode', 'builder');
    $doc->save(['elements' => $elements, 'settings' => $page_settings]);
    return $post_id;
}
/** Create or update a normal page built with Elementor. */
function el_page($key, $title, array $elements, $slug = null, array $settings = []) {
    $id = bstate("page_$key");
    if (!$id || !get_post($id)) {
        $existing = $slug ? get_page_by_path($slug) : null;
        $id = $existing ? $existing->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ?: sanitize_title($title)]);
        bstate("page_$key", $id);
    }
    wp_update_post(['ID' => $id, 'post_title' => $title, 'post_status' => 'publish', 'post_content' => '']);
    update_post_meta($id, '_wp_page_template', 'elementor_header_footer');
    el_save_post($id, $elements, $settings + ['template' => 'elementor_header_footer', 'hide_title' => 'yes']);
    return $id;
}
/** Create or update a Theme Builder / library template with display conditions. */
function el_template($key, $type, $title, array $elements, array $conditions = [], array $settings = []) {
    $id = bstate("tpl_$key");
    if (!$id || !get_post($id)) {
        $doc = \Elementor\Plugin::$instance->documents->create($type, ['post_title' => $title, 'post_status' => 'publish']);
        $id = $doc->get_main_id();
        bstate("tpl_$key", $id);
    }
    wp_update_post(['ID' => $id, 'post_title' => $title, 'post_status' => 'publish']);
    el_save_post($id, $elements, $settings);
    if ($conditions && class_exists('\ElementorPro\Modules\ThemeBuilder\Module')) {
        $c = array_map(function ($str) {
            $p = explode('/', $str);
            return ['type' => $p[0], 'name' => $p[1] ?? '', 'sub_name' => $p[2] ?? '', 'sub_id' => $p[3] ?? ''];
        }, $conditions);
        \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions($id, $c);
    }
    return $id;
}

/* ---------- Placeholder image generation (GD) ---------- */
function hex2rgb($h) { $h = ltrim($h, '#'); return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))]; }
function shade($hex, $f) { [$r, $g, $b] = hex2rgb($hex); $c = fn($v) => max(0, min(255, (int)round($f > 0 ? $v + (255 - $v) * $f : $v * (1 + $f)))); return sprintf('#%02x%02x%02x', $c($r), $c($g), $c($b)); }
function gcol($im, $hex, $alpha = 0) { [$r, $g, $b] = hex2rgb($hex); return imagecolorallocatealpha($im, $r, $g, $b, $alpha); }

function shape_points($shape) {
    $S = [
        'tshirt' => [[.35,.14],[.43,.12],[.50,.18],[.57,.12],[.65,.14],[.86,.30],[.77,.41],[.69,.36],[.69,.88],[.31,.88],[.31,.36],[.23,.41],[.14,.30]],
        'jacket' => [[.37,.10],[.50,.16],[.63,.10],[.79,.18],[.88,.80],[.77,.82],[.71,.42],[.71,.90],[.29,.90],[.29,.42],[.23,.82],[.12,.80],[.21,.18]],
        'pants'  => [[.31,.08],[.69,.08],[.74,.92],[.56,.92],[.50,.36],[.44,.92],[.26,.92]],
        'shoe'   => [[.10,.58],[.16,.44],[.30,.42],[.40,.38],[.52,.47],[.74,.53],[.88,.59],[.92,.68],[.10,.68]],
        'boot'   => [[.28,.16],[.52,.16],[.54,.52],[.80,.58],[.88,.66],[.88,.74],[.26,.74]],
        'bag'    => [[.22,.40],[.78,.40],[.85,.86],[.15,.86]],
    ];
    return $S[$shape] ?? [];
}
/**
 * Draw a product-style placeholder: soft gradient background + flat garment silhouette.
 */
function gen_image($path, $w, $h, $bgTop, $bgBot, $shape, $color, $opts = []) {
    $k = 2; $W = $w * $k; $H = $h * $k;
    $im = imagecreatetruecolor($W, $H);
    [$r1, $g1, $b1] = hex2rgb($bgTop); [$r2, $g2, $b2] = hex2rgb($bgBot);
    for ($y = 0; $y < $H; $y++) {
        $t = $y / $H;
        imagefilledrectangle($im, 0, $y, $W, $y, imagecolorallocate($im, (int)($r1 + ($r2 - $r1) * $t), (int)($g1 + ($g2 - $g1) * $t), (int)($b1 + ($b2 - $b1) * $t)));
    }
    // soft floor shadow
    $sh = gcol($im, '#000000', 112);
    $scale = $opts['scale'] ?? 0.72;
    $bw = min($W, $H) * $scale; $ox = ($W - $bw) / 2 + ($opts['dx'] ?? 0) * $W; $oy = ($H - $bw) / 2 + ($opts['dy'] ?? 0.02) * $H;
    imagefilledellipse($im, (int)($W / 2 + ($opts['dx'] ?? 0) * $W), (int)($oy + $bw * (["shoe"=>.77,"boot"=>.83,"cap"=>.66,"beanie"=>.77,"bag"=>.89][$shape] ?? .93)), (int)($bw * 0.75), (int)($bw * 0.07), $sh);
    $map = function ($pts) use ($bw, $ox, $oy) { $o = []; foreach ($pts as $p) { $o[] = (int)($ox + $p[0] * $bw); $o[] = (int)($oy + $p[1] * $bw); } return $o; };
    $fill = gcol($im, $color); $line = gcol($im, shade($color, ($opts['light'] ?? false) ? -0.18 : 0.16)); $dark = gcol($im, shade($color, -0.25));
    imagesetthickness($im, max(2, (int)($bw / 260)));
    if ($shape === 'cap') {
        imagefilledarc($im, (int)($ox + .48 * $bw), (int)($oy + .60 * $bw), (int)(.56 * $bw), (int)(.56 * $bw), 180, 360, $fill, IMG_ARC_PIE);
        imagefilledellipse($im, (int)($ox + .66 * $bw), (int)($oy + .60 * $bw), (int)(.52 * $bw), (int)(.09 * $bw), $dark);
        imagefilledellipse($im, (int)($ox + .48 * $bw), (int)($oy + .33 * $bw), (int)(.05 * $bw), (int)(.03 * $bw), $line);
    } elseif ($shape === 'beanie') {
        imagefilledarc($im, (int)($ox + .5 * $bw), (int)($oy + .62 * $bw), (int)(.56 * $bw), (int)(.66 * $bw), 180, 360, $fill, IMG_ARC_PIE);
        imagefilledrectangle($im, (int)($ox + .22 * $bw), (int)($oy + .58 * $bw), (int)($ox + .78 * $bw), (int)($oy + .74 * $bw), $dark);
        for ($i = 0; $i < 9; $i++) { $x = (int)($ox + (.25 + $i * .0625) * $bw); imageline($im, $x, (int)($oy + .6 * $bw), $x, (int)($oy + .72 * $bw), $line); }
    } else {
        $pts = $map(shape_points($shape));
        imagefilledpolygon($im, $pts, $fill);
        if ($shape === 'jacket') {
            imageline($im, (int)($ox + .5 * $bw), (int)($oy + .16 * $bw), (int)($ox + .5 * $bw), (int)($oy + .90 * $bw), $line);
            for ($i = 1; $i <= 6; $i++) { $y = (int)($oy + (.16 + $i * .11) * $bw); imageline($im, (int)($ox + .30 * $bw), $y, (int)($ox + .70 * $bw), $y, $line); }
            if (!empty($opts['fur'])) imagefilledellipse($im, (int)($ox + .5 * $bw), (int)($oy + .13 * $bw), (int)(.36 * $bw), (int)(.10 * $bw), gcol($im, $opts['fur']));
        } elseif ($shape === 'tshirt') {
            imagearc($im, (int)($ox + .5 * $bw), (int)($oy + .13 * $bw), (int)(.15 * $bw), (int)(.11 * $bw), 0, 180, $line);
            if (!empty($opts['print'])) imagefilledrectangle($im, (int)($ox + .42 * $bw), (int)($oy + .30 * $bw), (int)($ox + .58 * $bw), (int)($oy + .38 * $bw), gcol($im, $opts['print']));
        } elseif ($shape === 'pants') {
            imageline($im, (int)($ox + .31 * $bw), (int)($oy + .14 * $bw), (int)($ox + .69 * $bw), (int)($oy + .14 * $bw), $line);
            if (!empty($opts['pockets'])) { imagerectangle($im, (int)($ox + .29 * $bw), (int)($oy + .48 * $bw), (int)($ox + .39 * $bw), (int)($oy + .60 * $bw), $line); imagerectangle($im, (int)($ox + .61 * $bw), (int)($oy + .48 * $bw), (int)($ox + .71 * $bw), (int)($oy + .60 * $bw), $line); }
        } elseif ($shape === 'shoe' || $shape === 'boot') {
            $sole = gcol($im, $opts['sole'] ?? '#F2F0EC');
            $x1 = $shape === 'boot' ? .25 : .09; $x2 = $shape === 'boot' ? .89 : .93; $y1 = $shape === 'boot' ? .74 : .68;
            imagefilledrectangle($im, (int)($ox + $x1 * $bw), (int)($oy + $y1 * $bw), (int)($ox + $x2 * $bw), (int)($oy + ($y1 + .06) * $bw), $sole);
            if ($shape === 'shoe') for ($i = 0; $i < 4; $i++) { $x = (int)($ox + (.34 + $i * .045) * $bw); imageline($im, $x, (int)($oy + .43 * $bw), $x + (int)(.05 * $bw), (int)($oy + .50 * $bw), $line); }
            if (!empty($opts['accent'])) imagefilledellipse($im, (int)($ox + .62 * $bw), (int)($oy + .60 * $bw), (int)(.10 * $bw), (int)(.04 * $bw), gcol($im, $opts['accent']));
        } elseif ($shape === 'bag') {
            imagesetthickness($im, max(4, (int)($bw / 55)));
            imagearc($im, (int)($ox + .5 * $bw), (int)($oy + .40 * $bw), (int)(.36 * $bw), (int)(.40 * $bw), 180, 360, $dark);
            imagesetthickness($im, max(2, (int)($bw / 260)));
            imagefilledrectangle($im, (int)($ox + .46 * $bw), (int)($oy + .50 * $bw), (int)($ox + .54 * $bw), (int)($oy + .56 * $bw), gcol($im, GOLD));
            if (!empty($opts['quilt'])) for ($i = 1; $i < 6; $i++) { imageline($im, (int)($ox + (.15 + $i * .12) * $bw), (int)($oy + .40 * $bw), (int)($ox + (.15 + $i * .12 - .1) * $bw), (int)($oy + .86 * $bw), $line); }
        }
    }
    $out = imagecreatetruecolor($w, $h);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, $W, $H);
    imagejpeg($out, $path, 88);
    imagedestroy($im); imagedestroy($out);
}
/** Generate an image and register it in the Media Library (reused when already present). */
function media_image($key, $w, $h, $bgTop, $bgBot, $shape, $color, $opts = [], $title = null) {
    $id = bstate("img_$key");
    $regen = $id && get_post($id) && getenv('BTQ_REGEN');
    if ($id && get_post($id) && !$regen) return (int)$id;
    $up = wp_upload_dir();
    $file = $regen ? get_attached_file($id) : $up['path'] . "/boutique-$key.jpg";
    if (!empty($opts['custom'])) { ($opts['custom'])($file); } else { gen_image($file, $w, $h, $bgTop, $bgBot, $shape, $color, $opts); }
    if ($regen) { wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file)); return (int)$id; }
    $id = wp_insert_attachment(['post_mime_type' => 'image/jpeg', 'post_title' => $title ?: $key, 'post_status' => 'inherit'], $file);
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
    return (int)bstate("img_$key", $id);
}
function img($id) { return $id ? ['url' => wp_get_attachment_url($id), 'id' => $id, 'source' => 'library', 'alt' => ''] : ['url' => '', 'id' => '']; }
