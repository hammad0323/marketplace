<?php
/**
 * Generates the studio-style SAMPLE imagery used by the demo catalogue
 * (assets/img/sample/...). These are procedurally rendered placeholders —
 * replace them with real product photography from the admin panel before launch.
 *
 * Usage:  php tools/generate-sample-images.php
 * Requires the GD extension with FreeType.
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
ini_set('memory_limit', '1024M');
mt_srand(20261008);

const SS = 2; // supersampling factor
$OUT = dirname(__DIR__) . '/assets/img/sample';
$FONT_SERIF = __DIR__ . '/fonts/Cormorant-600.ttf';
$FONT_SANS = __DIR__ . '/fonts/Inter-500.ttf';
foreach (['products', 'hero', 'categories', 'story', 'misc'] as $d) {
    @mkdir("$OUT/$d", 0755, true);
}

// ---------------------------------------------------------------- colour utils
function hex2rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
function clamp255($v): int { return $v < 0 ? 0 : ($v > 255 ? 255 : (int) $v); }
function col($img, array $rgb, int $alpha = 0) { return imagecolorallocatealpha($img, clamp255($rgb[0]), clamp255($rgb[1]), clamp255($rgb[2]), max(0, min(127, $alpha))); }
function mix(array $a, array $b, float $t): array { return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t, $a[2] + ($b[2] - $a[2]) * $t]; }
function shade(array $c, float $f): array { return [$c[0] * $f, $c[1] * $f, $c[2] * $f]; }

function new_layer(int $w, int $h)
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    return $im;
}

/** Smooth value noise image (grey) of given size, produced by upscaling random pixels. */
function noise_img(int $w, int $h, int $cells)
{
    $small = imagecreatetruecolor($cells, max(2, (int) round($cells * $h / $w)));
    for ($y = 0; $y < imagesy($small); $y++) {
        for ($x = 0; $x < $cells; $x++) {
            $v = mt_rand(0, 255);
            imagesetpixel($small, $x, $y, ($v << 16) | ($v << 8) | $v);
        }
    }
    $big = imagecreatetruecolor($w, $h);
    imagecopyresampled($big, $small, 0, 0, 0, 0, $w, $h, imagesx($small), imagesy($small));
    imagedestroy($small);
    return $big;
}

function blur_img($img, int $times)
{
    for ($i = 0; $i < $times; $i++) {
        imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
    }
}

// ---------------------------------------------------------------- shape masks
function rrect($im, $x1, $y1, $x2, $y2, $r, $c)
{
    $r = min($r, ($x2 - $x1) / 2, ($y2 - $y1) / 2);
    imagefilledrectangle($im, (int) ($x1 + $r), (int) $y1, (int) ($x2 - $r), (int) $y2, $c);
    imagefilledrectangle($im, (int) $x1, (int) ($y1 + $r), (int) $x2, (int) ($y2 - $r), $c);
    $d = (int) ($r * 2);
    imagefilledellipse($im, (int) ($x1 + $r), (int) ($y1 + $r), $d, $d, $c);
    imagefilledellipse($im, (int) ($x2 - $r), (int) ($y1 + $r), $d, $d, $c);
    imagefilledellipse($im, (int) ($x1 + $r), (int) ($y2 - $r), $d, $d, $c);
    imagefilledellipse($im, (int) ($x2 - $r), (int) ($y2 - $r), $d, $d, $c);
}

/**
 * Paint leather inside a white-on-black mask onto a transparent layer.
 * Lighting: soft top-left key light, puffy edge falloff, two-scale grain.
 */
function paint_leather($layer, $mask, array $base, string $grain = 'smooth', float $gloss = 0.15)
{
    $w = imagesx($layer);
    $h = imagesy($layer);
    // Edge falloff map from a blurred low-res mask.
    $sw = max(8, (int) ($w / 10));
    $sh = max(8, (int) ($h / 10));
    $small = imagecreatetruecolor($sw, $sh);
    imagecopyresampled($small, $mask, 0, 0, 0, 0, $sw, $sh, $w, $h);
    blur_img($small, 4);
    $edge = imagecreatetruecolor($w, $h);
    imagecopyresampled($edge, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
    imagedestroy($small);
    $n1 = noise_img($w, $h, (int) max(8, $w / ($grain === 'pebble' ? 9 : 22)));
    $n2 = noise_img($w, $h, (int) max(8, $w / 160));
    // Bounding box of the mask.
    $minx = $w; $miny = $h; $maxx = 0; $maxy = 0;
    for ($y = 0; $y < $h; $y += 4) {
        for ($x = 0; $x < $w; $x += 4) {
            if ((imagecolorat($mask, $x, $y) & 0xFF) > 0) {
                $minx = min($minx, $x); $maxx = max($maxx, $x); $miny = min($miny, $y); $maxy = max($maxy, $y);
            }
        }
    }
    $minx = max(0, $minx - 4); $miny = max(0, $miny - 4); $maxx = min($w - 1, $maxx + 4); $maxy = min($h - 1, $maxy + 4);
    $bw = max(1, $maxx - $minx);
    $bh = max(1, $maxy - $miny);
    for ($y = $miny; $y <= $maxy; $y++) {
        $ty = ($y - $miny) / $bh;
        for ($x = $minx; $x <= $maxx; $x++) {
            $m = imagecolorat($mask, $x, $y) & 0xFF;
            if ($m === 0) {
                continue;
            }
            $tx = ($x - $minx) / $bw;
            $e = (imagecolorat($edge, $x, $y) & 0xFF) / 255;
            $edgeF = min(1, max(0, ($e - 0.35) * 2.4));
            $puff = 0.62 + 0.38 * pow($edgeF, 0.55);
            $light = 1.12 - 0.22 * $ty - 0.12 * $tx;
            $g1 = (imagecolorat($n1, $x, $y) & 0xFF) / 255;
            $g2 = (imagecolorat($n2, $x, $y) & 0xFF) / 255;
            $fine = mt_rand(-100, 100) / 100;
            if ($grain === 'pebble') {
                $tex = 0.86 + 0.2 * $g1 + 0.03 * $fine;
            } elseif ($grain === 'saffiano') {
                $tex = 0.9 + 0.08 * (($x + $y) % 6 < 2 ? 1 : 0) + 0.06 * $g1 + 0.03 * $fine;
            } else {
                $tex = 0.94 + 0.08 * $g2 + 0.03 * $g1 + 0.02 * $fine;
            }
            $f = $puff * $light * $tex;
            $spec = $gloss * max(0, 1 - abs($tx - 0.3) * 2.2) * max(0, 1 - $ty * 1.6) * $edgeF;
            $r = $base[0] * $f + 255 * $spec;
            $g = $base[1] * $f + 240 * $spec;
            $b = $base[2] * $f + 220 * $spec;
            $a = (int) round(127 - 127 * ($m / 255));
            imagesetpixel($layer, $x, $y, imagecolorallocatealpha($layer, clamp255($r), clamp255($g), clamp255($b), $a));
        }
    }
    imagedestroy($edge);
    imagedestroy($n1);
    imagedestroy($n2);
}

/** Saddle-stitch along a polyline (closed when $closed). */
function stitch($im, array $pts, array $thread, float $len, float $gap, float $thick, bool $closed = true)
{
    imagealphablending($im, true);
    $c = col($im, $thread, 10);
    $hole = col($im, [0, 0, 0], 75);
    $n = count($pts);
    $segs = $closed ? $n : $n - 1;
    for ($i = 0; $i < $segs; $i++) {
        [$x1, $y1] = $pts[$i];
        [$x2, $y2] = $pts[($i + 1) % $n];
        $dx = $x2 - $x1; $dy = $y2 - $y1;
        $dist = sqrt($dx * $dx + $dy * $dy);
        if ($dist < 1) {
            continue;
        }
        $ux = $dx / $dist; $uy = $dy / $dist;
        $px = -$uy * $thick / 2; $py = $ux * $thick / 2;
        for ($t = 0; $t + $len <= $dist; $t += $len + $gap) {
            $ax = $x1 + $ux * $t; $ay = $y1 + $uy * $t;
            $bx = $ax + $ux * $len; $by = $ay + $uy * $len;
            imagefilledellipse($im, (int) $ax, (int) $ay, (int) ($thick * 1.1), (int) ($thick * 1.1), $hole);
            imagefilledpolygon($im, [(int) ($ax + $px), (int) ($ay + $py), (int) ($bx + $px), (int) ($by + $py), (int) ($bx - $px), (int) ($by - $py), (int) ($ax - $px), (int) ($ay - $py)], $c);
        }
    }
    imagealphablending($im, false);
}

function rect_path($x1, $y1, $x2, $y2): array { return [[$x1, $y1], [$x2, $y1], [$x2, $y2], [$x1, $y2]]; }

/** Translucent line/shape helper (blending on). */
function overlay_line($im, $x1, $y1, $x2, $y2, array $rgb, int $alpha, int $thick)
{
    imagealphablending($im, true);
    imagesetthickness($im, $thick);
    imageline($im, (int) $x1, (int) $y1, (int) $x2, (int) $y2, col($im, $rgb, $alpha));
    imagesetthickness($im, 1);
    imagealphablending($im, false);
}

function overlay_rect($im, $x1, $y1, $x2, $y2, array $rgb, int $alpha, $r = 0)
{
    imagealphablending($im, true);
    $r ? rrect($im, $x1, $y1, $x2, $y2, $r, col($im, $rgb, $alpha)) : imagefilledrectangle($im, (int) $x1, (int) $y1, (int) $x2, (int) $y2, col($im, $rgb, $alpha));
    imagealphablending($im, false);
}

function deboss_text($im, string $text, float $size, $cx, $cy, array $base, string $font, float $spacing = 0.35)
{
    imagealphablending($im, true);
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $widths = [];
    $total = 0;
    foreach ($chars as $ch) {
        $bb = imagettfbbox($size, 0, $font, $ch);
        $widths[] = $bb[2] - $bb[0];
        $total += $bb[2] - $bb[0] + $size * $spacing;
    }
    $total -= $size * $spacing;
    $x = $cx - $total / 2;
    $y = $cy + $size / 2.6;
    foreach ($chars as $i => $ch) {
        imagettftext($im, $size, 0, (int) ($x + 1.5), (int) ($y + 2), col($im, [255, 240, 220], 105), $font, $ch);
        imagettftext($im, $size, 0, (int) $x, (int) $y, col($im, shade($base, 0.55), 40), $font, $ch);
        $x += $widths[$i] + $size * $spacing;
    }
    imagealphablending($im, false);
}

// ---------------------------------------------------------------- objects
/**
 * Render a product object onto its own transparent layer (supersampled).
 * Types: bifold, bifold_open, cardholder, minimalist, long, travel, keyfob, coinpouch
 */
function render_object(string $type, string $hex, string $grain = 'smooth', array $opt = [])
{
    global $FONT_SERIF;
    $base = hex2rgb($hex);
    $thread = $opt['thread'] ?? (array_sum($base) < 200 ? [190, 175, 150] : [235, 222, 196]);
    $s = SS;
    switch ($type) {
        case 'bifold':
            $W = 760 * $s; $H = 600 * $s; $pad = 30 * $s;
            $layer = new_layer($W, $H);
            $mask = imagecreatetruecolor($W, $H);
            rrect($mask, $pad, $pad, $W - $pad, $H - $pad, 34 * $s, imagecolorallocate($mask, 255, 255, 255));
            paint_leather($layer, $mask, $base, $grain);
            // fold on the left edge
            for ($i = 0; $i < 26 * $s; $i++) {
                overlay_line($layer, $pad + $i, $pad + 30 * $s, $pad + $i, $H - $pad - 30 * $s, [0, 0, 0], (int) (70 + $i * 2.2 / $s), 1);
            }
            $in = 22 * $s;
            stitch($layer, rect_path($pad + $in + 18 * $s, $pad + $in, $W - $pad - $in, $H - $pad - $in), $thread, 9 * $s, 5 * $s, 3 * $s);
            deboss_text($layer, 'BEGLET', 30 * $s, $W / 2 + 10 * $s, $H - $pad - 80 * $s, $base, $FONT_SERIF);
            break;
        case 'bifold_open':
            $W = 1300 * $s; $H = 620 * $s; $pad = 30 * $s;
            $layer = new_layer($W, $H);
            $mask = imagecreatetruecolor($W, $H);
            rrect($mask, $pad, $pad, $W - $pad, $H - $pad, 30 * $s, imagecolorallocate($mask, 255, 255, 255));
            paint_leather($layer, $mask, $base, $grain);
            $mid = $W / 2;
            for ($i = -18 * $s; $i < 18 * $s; $i++) {
                overlay_line($layer, $mid + $i, $pad + 6 * $s, $mid + $i, $H - $pad - 6 * $s, [0, 0, 0], 70 + (int) (abs($i) * 3 / $s), 1);
            }
            foreach ([[$pad + 40 * $s, $mid - 40 * $s], [$mid + 40 * $s, $W - $pad - 40 * $s]] as [$x1, $x2]) {
                for ($k = 0; $k < 4; $k++) {
                    $y = $pad + (90 + $k * 95) * $s;
                    overlay_rect($layer, $x1, $y, $x2, $y + 26 * $s, [0, 0, 0], 108);
                    overlay_line($layer, $x1, $y, $x2, $y, [0, 0, 0], 60, 3 * $s);
                    overlay_line($layer, $x1, $y + 3 * $s, $x2, $y + 3 * $s, [255, 230, 200], 112, 2 * $s);
                }
                stitch($layer, [[$x1, $pad + 70 * $s], [$x1, $H - $pad - 40 * $s], [$x2, $H - $pad - 40 * $s], [$x2, $pad + 70 * $s]], $thread, 9 * $s, 5 * $s, 3 * $s, false);
            }
            stitch($layer, rect_path($pad + 20 * $s, $pad + 20 * $s, $W - $pad - 20 * $s, $H - $pad - 20 * $s), $thread, 9 * $s, 5 * $s, 3 * $s);
            break;
        case 'cardholder':
        case 'minimalist':
            $W = 640 * $s; $H = 470 * $s; $pad = 30 * $s;
            if ($type === 'minimalist') { $W = 600 * $s; $H = 400 * $s; }
            $layer = new_layer($W, $H);
            // card peeking out
            $cardC = $opt['card'] ?? [16, 29, 53];
            $card = imagecreatetruecolor($W, $H);
            rrect($card, $pad + 50 * $s, $pad - 4 * $s, $W - $pad - 70 * $s, $pad + 200 * $s, 16 * $s, imagecolorallocate($card, 255, 255, 255));
            paint_leather($layer, $card, $cardC, 'smooth', 0.3);
            imagealphablending($layer, true);
            imagefilledrectangle($layer, $pad + 80 * $s, $pad + 26 * $s, $pad + 130 * $s, $pad + 60 * $s, col($layer, [185, 154, 91], 20));
            imagealphablending($layer, false);
            $body = new_layer($W, $H);
            $mask = imagecreatetruecolor($W, $H);
            rrect($mask, $pad, $pad + 60 * $s, $W - $pad, $H - $pad, 26 * $s, imagecolorallocate($mask, 255, 255, 255));
            paint_leather($body, $mask, $base, $grain);
            // slot curve
            imagealphablending($body, true);
            imagesetthickness($body, 4 * $s);
            imagearc($body, (int) ($W / 2), (int) ($pad + 60 * $s), (int) (200 * $s), (int) (90 * $s), 0, 180, col($body, [0, 0, 0], 70));
            imagesetthickness($body, 1);
            imagealphablending($body, false);
            stitch($body, [[$pad + 18 * $s, $pad + 80 * $s], [$pad + 18 * $s, $H - $pad - 18 * $s], [$W - $pad - 18 * $s, $H - $pad - 18 * $s], [$W - $pad - 18 * $s, $pad + 80 * $s]], $thread, 8 * $s, 5 * $s, 3 * $s, false);
            deboss_text($body, 'BEGLET', 24 * $s, $W / 2, $H - $pad - 70 * $s, $base, $FONT_SERIF);
            imagealphablending($layer, true);
            imagecopy($layer, $body, 0, 0, 0, 0, $W, $H);
            imagealphablending($layer, false);
            imagedestroy($body);
            break;
        case 'long':
            $W = 1180 * $s; $H = 560 * $s; $pad = 30 * $s;
            $layer = new_layer($W, $H);
            $mask = imagecreatetruecolor($W, $H);
            rrect($mask, $pad, $pad, $W - $pad, $H - $pad, 30 * $s, imagecolorallocate($mask, 255, 255, 255));
            paint_leather($layer, $mask, $base, $grain);
            // zip track along top and right
            $metal = [196, 166, 104];
            imagealphablending($layer, true);
            $zy = $pad + 26 * $s;
            imagefilledrectangle($layer, $pad + 40 * $s, $zy - 6 * $s, $W - $pad - 26 * $s, $zy + 6 * $s, col($layer, [20, 20, 22], 30));
            for ($x = $pad + 44 * $s; $x < $W - $pad - 30 * $s; $x += 9 * $s) {
                imagefilledrectangle($layer, $x, $zy - 5 * $s, $x + 4 * $s, $zy + 5 * $s, col($layer, $metal, 15));
            }
            rrect($layer, $W - $pad - 140 * $s, $zy - 14 * $s, $W - $pad - 70 * $s, $zy + 14 * $s, 8 * $s, col($layer, shade($metal, 1.08), 0));
            imagealphablending($layer, false);
            stitch($layer, rect_path($pad + 22 * $s, $pad + 50 * $s, $W - $pad - 22 * $s, $H - $pad - 22 * $s), $thread, 10 * $s, 5 * $s, 3 * $s);
            deboss_text($layer, 'BEGLET', 34 * $s, $W / 2, $H / 2 + 20 * $s, $base, $FONT_SERIF);
            break;
        case 'travel':
            $W = 640 * $s; $H = 880 * $s; $pad = 30 * $s;
            $layer = new_layer($W, $H);
            $mask = imagecreatetruecolor($W, $H);
            rrect($mask, $pad, $pad, $W - $pad, $H - $pad, 30 * $s, imagecolorallocate($mask, 255, 255, 255));
            paint_leather($layer, $mask, $base, $grain);
            stitch($layer, rect_path($pad + 22 * $s, $pad + 22 * $s, $W - $pad - 22 * $s, $H - $pad - 22 * $s), $thread, 10 * $s, 5 * $s, 3 * $s);
            deboss_text($layer, 'BEGLET', 34 * $s, $W / 2, $H * 0.42, $base, $FONT_SERIF);
            deboss_text($layer, 'TRAVEL', 16 * $s, $W / 2, $H * 0.42 + 54 * $s, $base, $FONT_SERIF, 0.6);
            // elastic band
            overlay_rect($layer, $W - $pad - 150 * $s, $pad - 2 * $s, $W - $pad - 120 * $s, $H - $pad + 2 * $s, [12, 12, 14], 25);
            break;
        case 'keyfob':
            $W = 760 * $s; $H = 420 * $s; $pad = 30 * $s;
            $layer = new_layer($W, $H);
            $mask = imagecreatetruecolor($W, $H);
            rrect($mask, $pad + 150 * $s, $pad + 90 * $s, $W - $pad, $H - $pad - 90 * $s, 90 * $s, imagecolorallocate($mask, 255, 255, 255));
            paint_leather($layer, $mask, $base, $grain);
            stitch($layer, rect_path($pad + 230 * $s, $pad + 112 * $s, $W - $pad - 60 * $s, $H - $pad - 112 * $s), $thread, 9 * $s, 5 * $s, 3 * $s);
            // metal split ring: filled outer disc, transparent centre, highlight
            $rx = (int) ($pad + 120 * $s);
            $ry = (int) ($H / 2);
            imagefilledellipse($layer, $rx, $ry, (int) (196 * $s), (int) (196 * $s), col($layer, [150, 124, 74], 0));
            imagefilledellipse($layer, $rx, $ry, (int) (186 * $s), (int) (186 * $s), col($layer, [196, 168, 110], 0));
            imagefilledellipse($layer, $rx, $ry, (int) (166 * $s), (int) (166 * $s), col($layer, [150, 124, 74], 0));
            imagefilledellipse($layer, $rx, $ry, (int) (160 * $s), (int) (160 * $s), imagecolorallocatealpha($layer, 0, 0, 0, 127));
            imagealphablending($layer, true);
            imagesetthickness($layer, 3 * $s);
            imagearc($layer, $rx, $ry, (int) (178 * $s), (int) (178 * $s), 200, 290, col($layer, [255, 244, 214], 20));
            imagesetthickness($layer, 1);
            imagealphablending($layer, false);
            // re-paint the leather loop over the ring where it passes through
            $loop = new_layer($W, $H);
            $lm = imagecreatetruecolor($W, $H);
            rrect($lm, $pad + 150 * $s, $pad + 90 * $s, $pad + 300 * $s, $H - $pad - 90 * $s, 90 * $s, imagecolorallocate($lm, 255, 255, 255));
            paint_leather($loop, $lm, $base, $grain);
            imagealphablending($layer, true);
            imagecopy($layer, $loop, 0, 0, 0, 0, $W, $H);
            imagealphablending($layer, false);
            imagedestroy($loop);
            imagedestroy($lm);
            deboss_text($layer, 'B', 46 * $s, $W - $pad - 150 * $s, $H / 2, $base, $FONT_SERIF);
            break;
        case 'coinpouch':
        default:
            $W = 640 * $s; $H = 560 * $s; $pad = 30 * $s;
            $layer = new_layer($W, $H);
            $mask = imagecreatetruecolor($W, $H);
            $white = imagecolorallocate($mask, 255, 255, 255);
            imagefilledellipse($mask, (int) ($W / 2), (int) ($H / 2 + 20 * $s), (int) ($W - 2 * $pad), (int) ($H - 2 * $pad - 40 * $s), $white);
            imagefilledrectangle($mask, $pad + 40 * $s, $pad + 20 * $s, $W - $pad - 40 * $s, (int) ($H / 2), $white);
            paint_leather($layer, $mask, $base, $grain);
            overlay_line($layer, $pad + 60 * $s, $pad + 120 * $s, $W - $pad - 60 * $s, $pad + 120 * $s, [0, 0, 0], 60, 4 * $s);
            stitch($layer, [[$pad + 70 * $s, $pad + 50 * $s], [$W - $pad - 70 * $s, $pad + 50 * $s]], $thread, 9 * $s, 5 * $s, 3 * $s, false);
            imagealphablending($layer, true);
            imagefilledellipse($layer, (int) ($W / 2), (int) ($pad + 160 * $s), 34 * $s, 34 * $s, col($layer, [190, 160, 100], 0));
            imagealphablending($layer, false);
            deboss_text($layer, 'BEGLET', 26 * $s, $W / 2, $H * 0.62, $base, $FONT_SERIF);
    }
    return $layer;
}

// ---------------------------------------------------------------- composition
function background(int $w, int $h, string $style)
{
    $im = imagecreatetruecolor($w, $h);
    $styles = [
        'ivory' => [[248, 245, 239], [232, 222, 205]],
        'beige' => [[236, 226, 209], [214, 198, 172]],
        'navy' => [[24, 40, 70], [8, 15, 30]],
        'midnight' => [[18, 30, 54], [6, 12, 24]],
        'stone' => [[226, 222, 214], [196, 189, 177]],
    ];
    [$top, $bot] = $styles[$style] ?? $styles['ivory'];
    for ($y = 0; $y < $h; $y++) {
        $c = mix($top, $bot, pow($y / $h, 1.2));
        imageline($im, 0, $y, $w, $y, col($im, $c));
    }
    // soft light pool + vignette
    $light = new_layer($w, $h);
    $dark = in_array($style, ['navy', 'midnight'], true);
    $steps = 40;
    for ($i = $steps; $i > 0; $i--) {
        $r = $i / $steps;
        imagefilledellipse($light, (int) ($w * 0.55), (int) ($h * 0.42), (int) ($w * 1.3 * $r), (int) ($h * 1.2 * $r), col($light, $dark ? [80, 110, 170] : [255, 252, 245], (int) (127 - (1 - $r) * ($dark ? 14 : 22))));
    }
    imagealphablending($im, true);
    imagecopy($im, $light, 0, 0, 0, 0, $w, $h);
    imagedestroy($light);
    // fine paper/linen grain
    $n = noise_img($w, $h, (int) ($w / 3));
    imagecopymerge($im, $n, 0, 0, 0, 0, $w, $h, 3);
    imagedestroy($n);
    return $im;
}

/** Place a supersampled layer onto a canvas with a soft contact shadow. */
function place($canvas, $layer, float $cx, float $cy, float $scale, float $angle = 0, float $shadowStrength = 0.55)
{
    if ($angle != 0) {
        $rot = imagerotate($layer, $angle, imagecolorallocatealpha($layer, 0, 0, 0, 127));
        imagesavealpha($rot, true);
        $layer = $rot;
    }
    $lw = imagesx($layer);
    $lh = imagesy($layer);
    $tw = (int) ($lw * $scale);
    $th = (int) ($lh * $scale);
    $x = (int) ($cx - $tw / 2);
    $y = (int) ($cy - $th / 2);
    // shadow from alpha at low resolution
    $sw = max(4, (int) ($tw / 8));
    $sh = max(4, (int) ($th / 8));
    $small = new_layer($sw + 8, $sh + 8);
    $tmp = new_layer($sw, $sh);
    imagecopyresampled($tmp, $layer, 0, 0, 0, 0, $sw, $sh, $lw, $lh);
    for ($yy = 0; $yy < $sh; $yy++) {
        for ($xx = 0; $xx < $sw; $xx++) {
            $a = (imagecolorat($tmp, $xx, $yy) >> 24) & 0x7F;
            if ($a < 127) {
                imagesetpixel($small, $xx + 4, $yy + 4, imagecolorallocatealpha($small, 5, 8, 15, (int) (127 - (127 - $a) * $shadowStrength)));
            }
        }
    }
    imagedestroy($tmp);
    // blur alpha by repeated down/up sampling
    for ($k = 0; $k < 3; $k++) {
        $half = new_layer(max(2, (int) (($sw + 8) / 2)), max(2, (int) (($sh + 8) / 2)));
        imagecopyresampled($half, $small, 0, 0, 0, 0, imagesx($half), imagesy($half), $sw + 8, $sh + 8);
        $small2 = new_layer($sw + 8, $sh + 8);
        imagecopyresampled($small2, $half, 0, 0, 0, 0, $sw + 8, $sh + 8, imagesx($half), imagesy($half));
        imagedestroy($small);
        imagedestroy($half);
        $small = $small2;
    }
    imagealphablending($canvas, true);
    $off = (int) (max($tw, $th) * 0.035);
    imagecopyresampled($canvas, $small, $x - (int) ($tw * 0.04) + $off / 2, $y - (int) ($th * 0.02) + $off, 0, 0, (int) ($tw * 1.08), (int) ($th * 1.08), $sw + 8, $sh + 8);
    imagedestroy($small);
    imagecopyresampled($canvas, $layer, $x, $y, 0, 0, $tw, $th, $lw, $lh);
    if ($angle != 0) {
        imagedestroy($layer);
    }
}

function save_jpg($im, string $path, int $w = 0, int $h = 0, int $q = 84)
{
    if ($w && $h && (imagesx($im) !== $w || imagesy($im) !== $h)) {
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
        $im = $out;
    }
    imageinterlace($im, true);
    imagejpeg($im, $path, $q);
    echo '  ✓ ' . str_replace(dirname(__DIR__) . '/', '', $path) . PHP_EOL;
}

function text_center($im, string $text, float $size, int $y, array $rgb, string $font, float $spacing = 0)
{
    imagealphablending($im, true);
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $total = 0;
    $ws = [];
    foreach ($chars as $ch) {
        $bb = imagettfbbox($size, 0, $font, $ch);
        $ws[] = $bb[2] - $bb[0];
        $total += $bb[2] - $bb[0] + $size * $spacing;
    }
    $x = (imagesx($im) - ($total - $size * $spacing)) / 2;
    foreach ($chars as $i => $ch) {
        imagettftext($im, $size, 0, (int) $x, $y, col($im, $rgb), $font, $ch);
        $x += $ws[$i] + $size * $spacing;
    }
}

// ---------------------------------------------------------------- products
$products = require __DIR__ . '/sample-catalogue.php';
$only = $argv[1] ?? '';
if ($only !== '') {
    $products = array_values(array_filter($products, fn($p) => strpos($p['slug'], $only) !== false || $p['shape'] === $only));
}
echo "Rendering product images…\n";
foreach ($products as $p) {
    foreach ($p['colors'] as $ci => $c) {
        $slug = $p['slug'] . ($ci ? '-' . $c['key'] : '');
        if ($ci > 1) {
            break; // two colourways rendered per product is plenty for the demo
        }
        $obj = render_object($p['shape'], $c['hex'], $p['grain'] ?? 'smooth', $p['opt'] ?? []);
        $W = 1000 * SS; $H = 1250 * SS;
        $fit = min(($W * 0.72) / imagesx($obj), ($H * 0.62) / imagesy($obj));
        // 1. studio shot
        $bg = background($W, $H, $p['bg'] ?? 'ivory');
        place($bg, $obj, $W / 2, $H * 0.5, $fit, $p['tilt'] ?? 0);
        save_jpg($bg, "$OUT/products/{$slug}-1.jpg", 1000, 1250);
        imagedestroy($bg);
        if ($ci === 0) {
            // 2. moody angle on navy
            $bg = background($W, $H, 'midnight');
            place($bg, $obj, $W * 0.52, $H * 0.52, $fit * 1.05, -14, 0.8);
            save_jpg($bg, "$OUT/products/{$slug}-2.jpg", 1000, 1250);
            imagedestroy($bg);
            // 3. detail crop (stitching & grain)
            $bg = background($W, $H, $p['bg'] === 'beige' ? 'stone' : 'beige');
            place($bg, $obj, $W * 0.62, $H * 0.62, $fit * 2.3, 8, 0.6);
            save_jpg($bg, "$OUT/products/{$slug}-3.jpg", 1000, 1250);
            imagedestroy($bg);
        }
        imagedestroy($obj);
    }
}

// ---------------------------------------------------------------- categories
echo "Rendering category images…\n";
$cats = [
    'bifold-wallets' => ['bifold', '#8B4A22', 'beige', 'pebble'],
    'minimalist-wallets' => ['minimalist', '#1D2C4D', 'ivory', 'smooth'],
    'card-holders' => ['cardholder', '#5A1F22', 'stone', 'saffiano'],
    'long-wallets' => ['long', '#2A1C14', 'beige', 'smooth'],
    'travel-wallets' => ['travel', '#A86A3D', 'navy', 'pebble'],
    'leather-accessories' => ['keyfob', '#3B2418', 'ivory', 'smooth'],
];
foreach ($cats as $slug => [$shape, $hex, $bgS, $grain]) {
    $W = 900 * SS; $H = 1100 * SS;
    $bg = background($W, $H, $bgS);
    $obj = render_object($shape, $hex, $grain);
    $fit = min(($W * 0.7) / imagesx($obj), ($H * 0.5) / imagesy($obj));
    place($bg, $obj, $W / 2, $H * 0.44, $fit, -6);
    save_jpg($bg, "$OUT/categories/$slug.jpg", 900, 1100);
    imagedestroy($bg);
    // wide banner for category page hero
    $W2 = 2000 * SS / 2; $H2 = 700 * SS / 2;
    $bg = background((int) $W2, (int) $H2, in_array($bgS, ['navy'], true) ? 'navy' : 'midnight');
    $fit2 = min(($W2 * 0.3) / imagesx($obj), ($H2 * 0.7) / imagesy($obj));
    place($bg, $obj, $W2 * 0.72, $H2 * 0.5, $fit2, -10, 0.8);
    save_jpg($bg, "$OUT/categories/$slug-banner.jpg", 2000, 700);
    imagedestroy($bg);
    imagedestroy($obj);
}

// ---------------------------------------------------------------- hero slides
echo "Rendering hero slides…\n";
$heroSets = [
    'hero-1' => ['midnight', [['bifold_open', '#8B4A22', 'pebble', 0.66, 0.46, 0.34, -8], ['cardholder', '#1C1B1D', 'saffiano', 0.86, 0.72, 0.2, 12], ['keyfob', '#A86A3D', 'smooth', 0.52, 0.78, 0.17, -24]]],
    'hero-2' => ['beige', [['long', '#3B2418', 'smooth', 0.68, 0.42, 0.34, 6], ['bifold', '#1D2C4D', 'pebble', 0.83, 0.7, 0.22, -12], ['minimalist', '#8A7967', 'smooth', 0.55, 0.74, 0.18, 18]]],
    'hero-3' => ['navy', [['travel', '#5A1F22', 'pebble', 0.74, 0.5, 0.36, -10], ['coinpouch', '#A86A3D', 'smooth', 0.56, 0.7, 0.18, 14]]],
];
foreach ($heroSets as $name => [$bgS, $items]) {
    $W = 2400; $H = 1200;
    $bg = background($W, $H, $bgS);
    foreach ($items as [$shape, $hex, $grain, $x, $y, $wfrac, $ang]) {
        $obj = render_object($shape, $hex, $grain);
        place($bg, $obj, $W * $x, $H * $y, ($W * $wfrac) / imagesx($obj), $ang, 0.75);
        imagedestroy($obj);
    }
    save_jpg($bg, "$OUT/hero/$name-desktop.jpg", 2400, 1200, 82);
    imagedestroy($bg);
    $W = 900; $H = 1400;
    $bg = background($W, $H, $bgS);
    foreach ($items as $k => [$shape, $hex, $grain, $x, $y, $wfrac, $ang]) {
        $obj = render_object($shape, $hex, $grain);
        $mx = [0.5, 0.7, 0.3][$k] ?? 0.5;
        $my = [0.34, 0.5, 0.52][$k] ?? 0.4;
        place($bg, $obj, $W * $mx, $H * $my, ($W * $wfrac * 2.1) / imagesx($obj), $ang, 0.75);
        imagedestroy($obj);
    }
    save_jpg($bg, "$OUT/hero/$name-mobile.jpg", 900, 1400, 82);
    imagedestroy($bg);
}

// ---------------------------------------------------------------- story & misc
echo "Rendering editorial images…\n";
// Swatch fan (materials story)
$W = 1200 * SS; $H = 1500 * SS;
$bg = background($W, $H, 'beige');
$sw = ['#3B2418', '#5A1F22', '#8B4A22', '#A86A3D', '#1D2C4D', '#1C1B1D'];
foreach ($sw as $i => $hex) {
    $sz = 520 * SS;
    $layer = new_layer($sz, (int) ($sz * 1.35));
    $mask = imagecreatetruecolor($sz, (int) ($sz * 1.35));
    rrect($mask, 20 * SS, 20 * SS, $sz - 20 * SS, $sz * 1.35 - 20 * SS, 26 * SS, imagecolorallocate($mask, 255, 255, 255));
    paint_leather($layer, $mask, hex2rgb($hex), $i % 2 ? 'pebble' : 'smooth');
    stitch($layer, rect_path(48 * SS, 48 * SS, $sz - 48 * SS, $sz * 1.35 - 48 * SS), [225, 210, 180], 9 * SS, 5 * SS, 3 * SS);
    place($bg, $layer, $W * 0.5 + ($i - 2.5) * 40 * SS, $H * 0.52, 1.0, 40 - $i * 16, 0.5);
    imagedestroy($layer);
    imagedestroy($mask);
}
save_jpg($bg, "$OUT/story/materials.jpg", 1200, 1500);
imagedestroy($bg);
// Stitch detail
$W = 800 * SS; $H = 800 * SS;
$bg = background($W, $H, 'midnight');
$obj = render_object('bifold', '#8B4A22', 'pebble');
place($bg, $obj, $W * 0.7, $H * 0.72, 2.6 / SS * 1.0, 10, 0.6);
save_jpg($bg, "$OUT/story/stitch-detail.jpg", 700, 700);
imagedestroy($bg);
imagedestroy($obj);
// Featured collection
$W = 1200 * SS / 2; $H = 1500 * SS / 2;
$bg = background((int) $W, (int) $H, 'navy');
foreach ([['bifold', '#1C1B1D', 0.5, 0.36, -8], ['cardholder', '#1D2C4D', 0.55, 0.62, 10], ['keyfob', '#1C1B1D', 0.4, 0.82, -18]] as [$shape, $hex, $x, $y, $a]) {
    $obj = render_object($shape, $hex, 'pebble', ['thread' => [185, 154, 91]]);
    place($bg, $obj, $W * $x, $H * $y, ($W * 0.62) / imagesx($obj), $a, 0.8);
    imagedestroy($obj);
}
save_jpg($bg, "$OUT/misc/collection-noir.jpg", 1200, 1500);
imagedestroy($bg);
// Newsletter / texture background
$W = 1600; $H = 800;
$layer = new_layer($W, $H);
$mask = imagecreatetruecolor($W, $H);
imagefilledrectangle($mask, 0, 0, $W, $H, imagecolorallocate($mask, 255, 255, 255));
paint_leather($layer, $mask, hex2rgb('#2A1C14'), 'pebble', 0.05);
$bg = imagecreatetruecolor($W, $H);
imagecopy($bg, $layer, 0, 0, 0, 0, $W, $H);
save_jpg($bg, "$OUT/misc/leather-texture.jpg", 1600, 800, 80);
imagedestroy($bg);
// Mega menu image
$W = 1200; $H = 800;
$bg = background($W, $H, 'beige');
$obj = render_object('bifold_open', '#A86A3D', 'smooth');
place($bg, $obj, $W / 2, $H / 2, ($W * 0.8) / imagesx($obj), -4);
save_jpg($bg, "$OUT/misc/mega-menu.jpg", 600, 400);
imagedestroy($bg);
imagedestroy($obj);
// Open Graph default
$W = 1200; $H = 630;
$bg = background($W, $H, 'midnight');
text_center($bg, 'BEGLET', 92, 330, [247, 245, 240], $FONT_SERIF, 0.32);
imagealphablending($bg, true);
imagefilledrectangle($bg, 560, 372, 640, 373, col($bg, [185, 154, 91]));
text_center($bg, 'PREMIUM CRAFTED LEATHER', 18, 420, [185, 154, 91], $FONT_SANS, 0.45);
$dir = dirname(__DIR__) . '/assets/img/brand';
@mkdir($dir, 0755, true);
save_jpg($bg, "$dir/og-default.jpg", 1200, 630, 86);
echo "Done.\n";
