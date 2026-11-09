<?php
/**
 * Generates the sample artwork used by the professional menu templates:
 * transparent "cut-out" dish illustrations, decorations and textured
 * backgrounds. Drawn with GD at 2x and downsampled for smooth edges.
 *
 * These are placeholders that look good on a menu board; admins replace them
 * with real food photos in the web template editor (any PNG with a transparent
 * background or JPG works).
 */

function art_canvas(int $w, int $h): GdImage
{
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    imagesavealpha($im, true);
    return $im;
}

function art_col(GdImage $im, string $hex, int $alpha = 0): int
{
    $h = ltrim($hex, '#');
    return imagecolorallocatealpha($im, hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2)), max(0, min(127, $alpha)));
}

function art_ellipse(GdImage $im, float $cx, float $cy, float $w, float $h, string $hex, int $alpha = 0): void
{
    imagefilledellipse($im, (int)$cx, (int)$cy, (int)$w, (int)$h, art_col($im, $hex, $alpha));
}

/** Rotated polygon helper: $pts are [x,y] pairs relative to (0,0). */
function art_poly(GdImage $im, array $pts, float $cx, float $cy, float $angle, string $hex, int $alpha = 0): void
{
    $flat = [];
    $c = cos($angle); $s = sin($angle);
    foreach ($pts as [$x, $y]) { $flat[] = (int)($cx + $x * $c - $y * $s); $flat[] = (int)($cy + $x * $s + $y * $c); }
    imagefilledpolygon($im, $flat, art_col($im, $hex, $alpha));
}

function art_leaf(GdImage $im, float $cx, float $cy, float $len, float $wid, float $angle, string $hex, ?string $vein = null): void
{
    $pts = [];
    for ($i = 0; $i <= 16; $i++) { $t = $i / 16; $pts[] = [($t - 0.5) * $len, sin(M_PI * $t) * $wid / 2]; }
    for ($i = 16; $i >= 0; $i--) { $t = $i / 16; $pts[] = [($t - 0.5) * $len, -sin(M_PI * $t) * $wid / 2 * 0.9]; }
    art_poly($im, $pts, $cx, $cy, $angle, $hex);
    if ($vein) {
        imagesetthickness($im, max(2, (int)($wid / 18)));
        $c = cos($angle); $s = sin($angle);
        imageline($im, (int)($cx - $len * 0.45 * $c), (int)($cy - $len * 0.45 * $s), (int)($cx + $len * 0.45 * $c), (int)($cy + $len * 0.45 * $s), art_col($im, $vein, 40));
        imagesetthickness($im, 1);
    }
}

/** Soft elliptical shadow built from many translucent rings. */
function art_shadow(GdImage $im, float $cx, float $cy, float $w, float $h, int $steps = 28): void
{
    for ($i = 0; $i < $steps; $i++) {
        $k = 1.12 - $i * (0.3 / $steps);
        art_ellipse($im, $cx, $cy, $w * $k, $h * $k, '#000000', 124);
    }
}

function art_bowl(GdImage $im, float $cx, float $cy, float $d, string $rim = '#f7f7f7', string $inner = '#ffffff'): void
{
    art_shadow($im, $cx + $d * 0.02, $cy + $d * 0.06, $d * 0.98, $d * 0.95);
    art_ellipse($im, $cx, $cy, $d, $d, '#dcdcdc');
    art_ellipse($im, $cx, $cy, $d * 0.985, $d * 0.985, $rim);
    art_ellipse($im, $cx, $cy, $d * 0.86, $d * 0.86, '#e9e9e9');
    art_ellipse($im, $cx, $cy, $d * 0.845, $d * 0.845, $inner);
}

function art_save(GdImage $big, int $w, int $h, string $path, bool $jpeg = false): void
{
    $out = $jpeg ? imagecreatetruecolor($w, $h) : art_canvas($w, $h);
    if (!$jpeg) imagealphablending($out, false);
    imagecopyresampled($out, $big, 0, 0, 0, 0, $w, $h, imagesx($big), imagesy($big));
    @mkdir(dirname($path), 0775, true);
    $jpeg ? imagejpeg($out, $path, 84) : imagepng($out, $path, 6);
}

function rnd(float $a, float $b): float { return $a + mt_rand() / mt_getrandmax() * ($b - $a); }
function pick(array $a) { return $a[mt_rand(0, count($a) - 1)]; }
function in_circle(float $r): array { $t = rnd(0, 2 * M_PI); $d = sqrt(rnd(0, 1)) * $r; return [cos($t) * $d, sin($t) * $d]; }

// ---------------------------------------------------------------------------
// Dishes (transparent PNG, square)
// ---------------------------------------------------------------------------
function art_cherry_tomato(GdImage $im, float $x, float $y, float $d, bool $half = false): void
{
    art_ellipse($im, $x + $d * 0.05, $y + $d * 0.07, $d, $d, '#000000', 105);
    art_ellipse($im, $x, $y, $d, $d, '#d62f24');
    if ($half) {
        art_ellipse($im, $x, $y, $d * 0.78, $d * 0.78, '#f05a3c');
        for ($k = 0; $k < 3; $k++) { $a = $k * 2.1; art_ellipse($im, $x + cos($a) * $d * 0.2, $y + sin($a) * $d * 0.2, $d * 0.22, $d * 0.3, '#ffb199'); }
    } else {
        art_ellipse($im, $x - $d * 0.2, $y - $d * 0.22, $d * 0.28, $d * 0.2, '#ffffff', 70);
        for ($k = 0; $k < 5; $k++) art_leaf($im, $x + cos($k * 1.256) * $d * 0.13, $y + sin($k * 1.256) * $d * 0.13, $d * 0.3, $d * 0.09, $k * 1.256, '#3d7a2a');
    }
}

function art_salad(string $path): void
{
    mt_srand(7);
    $S = 1600; $im = art_canvas($S, $S); $c = $S / 2;
    art_bowl($im, $c, $c, 1420);
    $greens = ['#2f7a2a', '#3f8f2f', '#5aa83a', '#79c24a', '#a3d16b', '#4d9b35'];
    for ($i = 0; $i < 130; $i++) { [$x, $y] = in_circle(520); art_leaf($im, $c + $x, $c + $y, rnd(170, 300), rnd(80, 140), rnd(0, M_PI * 2), pick($greens), '#d9efc0'); }
    for ($i = 0; $i < 9; $i++) { // avocado
        [$x, $y] = in_circle(380); $a = rnd(0, M_PI);
        art_leaf($im, $c + $x, $c + $y, 230, 120, $a, '#5c8a2c'); art_leaf($im, $c + $x, $c + $y, 210, 100, $a, '#c9e27a');
    }
    for ($i = 0; $i < 10; $i++) { // cucumber
        [$x, $y] = in_circle(430);
        art_ellipse($im, $c + $x, $c + $y, 128, 128, '#3e7d2a'); art_ellipse($im, $c + $x, $c + $y, 112, 112, '#d4eaa0');
        for ($k = 0; $k < 6; $k++) art_ellipse($im, $c + $x + cos($k) * 26, $c + $y + sin($k) * 26, 12, 20, '#f4f9e4');
    }
    for ($i = 0; $i < 11; $i++) { // chicken
        [$x, $y] = in_circle(360); $a = rnd(0, M_PI);
        art_poly($im, [[-90, -45], [80, -55], [100, 30], [-70, 55]], $c + $x, $c + $y, $a, '#e9c995');
        imagesetthickness($im, 10);
        for ($g = -1; $g <= 1; $g++) imageline($im, (int)($c + $x + $g * 40 - 30), (int)($c + $y - 30), (int)($c + $x + $g * 40 + 20), (int)($c + $y + 35), art_col($im, '#a9733a', 30));
        imagesetthickness($im, 1);
    }
    for ($i = 0; $i < 12; $i++) { [$x, $y] = in_circle(470); art_cherry_tomato($im, $c + $x, $c + $y, rnd(95, 125), (bool)mt_rand(0, 1)); }
    for ($i = 0; $i < 14; $i++) { [$x, $y] = in_circle(420); art_poly($im, [[-22, -20], [24, -18], [20, 22], [-20, 20]], $c + $x, $c + $y, rnd(0, 1.5), '#c99a55'); }
    art_save($im, 800, 800, $path);
}

function art_pasta(string $path): void
{
    mt_srand(11);
    $S = 1600; $im = art_canvas($S, $S); $c = $S / 2;
    art_bowl($im, $c, $c, 1400);
    art_ellipse($im, $c, $c, 1000, 1000, '#c63d22', 90);
    imagesetthickness($im, 22);
    for ($i = 0; $i < 170; $i++) {
        [$x, $y] = in_circle(420); $r = rnd(80, 260);
        imagearc($im, (int)($c + $x), (int)($c + $y), (int)$r, (int)($r * rnd(0.6, 1.2)), (int)rnd(0, 360), (int)rnd(0, 360), art_col($im, pick(['#f2b84b', '#eaa53a', '#f6c766', '#e09a2e'])));
    }
    imagesetthickness($im, 1);
    for ($i = 0; $i < 24; $i++) { [$x, $y] = in_circle(360); art_ellipse($im, $c + $x, $c + $y, rnd(60, 130), rnd(50, 110), pick(['#b9281c', '#d23a26', '#a51f17'])); }
    for ($i = 0; $i < 7; $i++) { [$x, $y] = in_circle(260); art_leaf($im, $c + $x, $c + $y, 230, 140, rnd(0, 6), '#2e7d32', '#a5d6a7'); }
    for ($i = 0; $i < 60; $i++) { [$x, $y] = in_circle(380); art_ellipse($im, $c + $x, $c + $y, 16, 12, '#fff8e1'); }
    art_save($im, 800, 800, $path);
}

function art_fruit(string $path): void
{
    mt_srand(23);
    $S = 1600; $im = art_canvas($S, $S); $c = $S / 2;
    art_bowl($im, $c, $c, 1380);
    for ($i = 0; $i < 13; $i++) { // kiwi
        [$x, $y] = in_circle(420); $d = rnd(190, 230);
        art_ellipse($im, $c + $x, $c + $y, $d, $d, '#7a5a2b'); art_ellipse($im, $c + $x, $c + $y, $d * 0.92, $d * 0.92, '#8bc34a');
        art_ellipse($im, $c + $x, $c + $y, $d * 0.4, $d * 0.4, '#e6f2c4');
        for ($k = 0; $k < 14; $k++) { $a = $k * 0.449; art_ellipse($im, $c + $x + cos($a) * $d * 0.26, $c + $y + sin($a) * $d * 0.26, 10, 16, '#1b1b1b'); }
    }
    for ($i = 0; $i < 14; $i++) { // strawberries
        [$x, $y] = in_circle(430); $a = rnd(0, 6);
        art_poly($im, [[0, -90], [70, -55], [80, 10], [30, 85], [-30, 85], [-80, 10], [-70, -55]], $c + $x, $c + $y, $a, '#d81b3c');
        for ($k = 0; $k < 9; $k++) art_ellipse($im, $c + $x + rnd(-45, 45), $c + $y + rnd(-45, 45), 9, 13, '#ffe082');
        art_leaf($im, $c + $x + cos($a - M_PI / 2) * 85, $c + $y + sin($a - M_PI / 2) * 85, 90, 40, $a, '#388e3c');
    }
    for ($i = 0; $i < 26; $i++) { [$x, $y] = in_circle(460); art_ellipse($im, $c + $x, $c + $y, 78, 78, '#283593'); art_ellipse($im, $c + $x - 14, $c + $y - 14, 22, 22, '#9fa8da', 60); }
    for ($i = 0; $i < 12; $i++) { [$x, $y] = in_circle(400); art_poly($im, [[-80, -30], [0, -60], [80, -30], [60, 30], [-60, 30]], $c + $x, $c + $y, rnd(0, 6), '#ffa726'); }
    art_save($im, 800, 800, $path);
}

function art_steak(string $path): void
{
    mt_srand(31);
    $S = 1600; $im = art_canvas($S, $S); $c = $S / 2;
    art_bowl($im, $c, $c, 1440, '#fafafa', '#ffffff');
    art_poly($im, [[-330, -170], [180, -230], [360, -40], [250, 200], [-200, 230], [-380, 40]], $c - 90, $c - 40, 0.2, '#5d2d17');
    art_poly($im, [[-300, -150], [160, -205], [330, -40], [225, 180], [-185, 205], [-350, 35]], $c - 90, $c - 40, 0.2, '#7b3f22');
    imagesetthickness($im, 26);
    for ($g = 0; $g < 5; $g++) imageline($im, (int)($c - 420 + $g * 140), (int)($c - 260), (int)($c - 220 + $g * 140), (int)($c + 180), art_col($im, '#3e1c0d', 20));
    imagesetthickness($im, 1);
    for ($i = 0; $i < 26; $i++) { // fries
        $x = $c + rnd(120, 470); $y = $c + rnd(80, 480); $a = rnd(-1.2, 1.2);
        art_poly($im, [[-140, -22], [140, -22], [140, 22], [-140, 22]], $x, $y, $a, '#d9a521');
        art_poly($im, [[-132, -15], [132, -15], [132, 15], [-132, 15]], $x, $y, $a, '#f6c945');
    }
    for ($i = 0; $i < 18; $i++) art_leaf($im, $c + rnd(-460, -180), $c + rnd(220, 480), rnd(120, 200), rnd(60, 100), rnd(0, 6), pick(['#3f8f2f', '#79c24a', '#5aa83a']), '#d9efc0');
    art_cherry_tomato($im, $c - 300, $c + 330, 110, true);
    art_ellipse($im, $c + 330, $c - 330, 260, 260, '#e0e0e0'); art_ellipse($im, $c + 330, $c - 330, 220, 220, '#8d2b14');
    art_save($im, 800, 800, $path);
}

function art_burger(string $path): void
{
    mt_srand(41);
    $S = 1600; $im = art_canvas($S, $S); $c = $S / 2;
    art_shadow($im, $c, $c + 560, 1250, 220);
    art_poly($im, [[-560, 0], [560, 0], [520, 130], [-520, 130]], $c, $c + 380, 0, '#c98a3b');   // bottom bun
    art_ellipse($im, $c, $c + 300, 1180, 230, '#4e2a16');                                          // patty
    art_ellipse($im, $c, $c + 270, 1150, 190, '#6b3a1f');
    art_poly($im, [[-600, -10], [600, -10], [420, 120], [250, 40], [60, 150], [-140, 50], [-380, 140]], $c, $c + 210, 0, '#ffc107'); // cheese
    for ($k = -6; $k <= 6; $k++) art_ellipse($im, $c + $k * 95, $c + 150, 190, 120, $k % 2 ? '#7cb342' : '#9ccc65'); // lettuce
    art_ellipse($im, $c - 260, $c + 90, 380, 110, '#e53935'); art_ellipse($im, $c + 250, $c + 95, 380, 110, '#d32f2f');
    // top bun dome
    imagefilledarc($im, (int)$c, (int)($c + 60), 1200, 900, 180, 360, art_col($im, '#c98a3b'), IMG_ARC_PIE);
    imagefilledarc($im, (int)$c, (int)($c + 60), 1160, 860, 180, 360, art_col($im, '#e0a453'), IMG_ARC_PIE);
    art_ellipse($im, $c - 200, $c - 240, 380, 160, '#f2c27a', 60);
    for ($i = 0; $i < 34; $i++) { $a = rnd(M_PI * 1.08, M_PI * 1.92); $r = rnd(0.25, 0.92); art_leaf($im, $c + cos($a) * 560 * $r, $c + 60 + sin($a) * 420 * $r, 40, 18, rnd(0, 3), '#fff3d6'); }
    art_save($im, 800, 800, $path);
}

function art_coffee(string $path): void
{
    $S = 1600; $im = art_canvas($S, $S); $c = $S / 2;
    art_bowl($im, $c, $c, 1300, '#fafafa', '#f4f4f4');
    art_shadow($im, $c + 20, $c + 50, 860, 840, 18);
    art_ellipse($im, $c, $c, 860, 860, '#ffffff');
    art_ellipse($im, $c, $c, 740, 740, '#5d3a25');
    art_ellipse($im, $c, $c, 700, 700, '#8a5a37');
    art_ellipse($im, $c - 70, $c - 40, 240, 240, '#efe0c9'); art_ellipse($im, $c + 70, $c - 40, 240, 240, '#efe0c9');
    art_poly($im, [[-185, 0], [185, 0], [0, 220]], $c, $c - 20, 0, '#efe0c9');
    art_poly($im, [[-70, -40], [70, -40], [80, 40], [-80, 40]], $c + 520, $c + 20, 0, '#ffffff');
    art_save($im, 800, 800, $path);
}

function art_noodles(string $path): void
{
    mt_srand(53);
    $S = 1600; $im = art_canvas($S, $S); $c = $S / 2;
    art_shadow($im, $c + 20, $c + 70, 1400, 1360);
    art_ellipse($im, $c, $c, 1420, 1420, '#b71c1c'); art_ellipse($im, $c, $c, 1340, 1340, '#212121');
    art_ellipse($im, $c, $c, 1200, 1200, '#d9a35b');
    imagesetthickness($im, 16);
    for ($i = 0; $i < 140; $i++) { [$x, $y] = in_circle(420); $r = rnd(120, 320); imagearc($im, (int)($c + $x), (int)($c + $y), (int)$r, (int)($r * 0.7), (int)rnd(0, 360), (int)rnd(0, 360), art_col($im, pick(['#fff1c1', '#f7e3a1', '#fbe9b7']))); }
    imagesetthickness($im, 1);
    for ($i = 0; $i < 5; $i++) { // shrimp
        $a = $i * 1.25; $x = $c + cos($a) * 330; $y = $c + sin($a) * 330;
        imagesetthickness($im, 60); imagearc($im, (int)$x, (int)$y, 200, 200, (int)rad2deg($a) + 20, (int)rad2deg($a) + 250, art_col($im, '#ff7043')); imagesetthickness($im, 1);
    }
    art_ellipse($im, $c + 60, $c - 40, 280, 280, '#ffffff'); art_ellipse($im, $c + 60, $c - 40, 150, 150, '#ffb300');
    for ($i = 0; $i < 40; $i++) { [$x, $y] = in_circle(500); art_ellipse($im, $c + $x, $c + $y, 46, 46, '#43a047'); art_ellipse($im, $c + $x, $c + $y, 22, 22, '#c5e1a5'); }
    art_save($im, 800, 800, $path);
}

function art_deco_tomato(string $path): void { $im = art_canvas(600, 600); art_cherry_tomato($im, 300, 300, 440); art_save($im, 300, 300, $path); }
function art_deco_basil(string $path): void
{
    $im = art_canvas(800, 800);
    art_leaf($im, 330, 420, 520, 270, -0.6, '#2e7d32', '#a5d6a7'); art_leaf($im, 470, 380, 480, 240, 0.5, '#388e3c', '#a5d6a7'); art_leaf($im, 400, 300, 420, 220, -1.5, '#43a047', '#c8e6c9');
    art_save($im, 400, 400, $path);
}
function art_deco_kiwi(string $path): void
{
    $im = art_canvas(600, 600);
    art_ellipse($im, 300, 300, 520, 520, '#7a5a2b'); art_ellipse($im, 300, 300, 480, 480, '#8bc34a'); art_ellipse($im, 300, 300, 200, 200, '#e6f2c4');
    for ($k = 0; $k < 18; $k++) { $a = $k * 0.349; art_ellipse($im, 300 + cos($a) * 135, 300 + sin($a) * 135, 22, 34, '#1b1b1b'); }
    art_save($im, 300, 300, $path);
}

// ---------------------------------------------------------------------------
// Backgrounds (JPEG)
// ---------------------------------------------------------------------------
function art_texture(int $w, int $h, string $base, int $spread, int $seed, string $path, bool $streaks = true): void
{
    mt_srand($seed);
    $im = imagecreatetruecolor($w, $h);
    $b = sscanf(ltrim($base, '#'), '%02x%02x%02x');
    imagefill($im, 0, 0, imagecolorallocate($im, ...$b));
    // large soft blotches: tiny random image scaled up
    $small = imagecreatetruecolor(48, 27);
    for ($x = 0; $x < 48; $x++) for ($y = 0; $y < 27; $y++) { $d = mt_rand(-$spread, $spread); imagesetpixel($small, $x, $y, imagecolorallocate($small, ...array_map(fn($v) => max(0, min(255, $v + $d)), $b))); }
    // progressive upscale + blur = smooth cloudy texture (no blocks)
    $cur = $small;
    foreach ([[192, 108], [480, 270], [$w, $h]] as [$nw, $nh]) {
        $next = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($next, $cur, 0, 0, 0, 0, $nw, $nh, imagesx($cur), imagesy($cur));
        for ($k = 0; $k < 4; $k++) imagefilter($next, IMG_FILTER_GAUSSIAN_BLUR);
        $cur = $next;
    }
    $blot = $cur;
    imagecopymerge($im, $blot, 0, 0, 0, 0, $w, $h, 70);
    // fine grain
    for ($i = 0; $i < $w * $h / 6; $i++) { $d = mt_rand(-$spread, $spread) / 2; imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocate($im, ...array_map(fn($v) => (int)max(0, min(255, $v + $d)), $b))); }
    if ($streaks) {
        for ($i = 0; $i < 40; $i++) {
            $x = mt_rand(0, $w); $len = mt_rand(80, 400);
            imageline($im, $x, mt_rand(0, $h), $x + mt_rand(-60, 60), mt_rand(0, $h) + $len, imagecolorallocatealpha($im, 0, 0, 0, 118));
        }
    }
    @mkdir(dirname($path), 0775, true);
    imagejpeg($im, $path, 84);
}

function art_burst(int $w, int $h, string $path): void
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, art_col($im, '#ffc928'));
    $cx = $w / 2; $cy = $h * 0.42;
    for ($i = 0; $i < 24; $i += 2) {
        $a1 = $i * M_PI / 12; $a2 = ($i + 1) * M_PI / 12; $r = $h * 1.4;
        imagefilledpolygon($im, [(int)$cx, (int)$cy, (int)($cx + cos($a1) * $r), (int)($cy + sin($a1) * $r), (int)($cx + cos($a2) * $r), (int)($cy + sin($a2) * $r)], art_col($im, '#ffd84d'));
    }
    imagejpeg($im, $path, 86);
}

function art_wood(int $w, int $h, string $path): void
{
    mt_srand(77);
    $im = imagecreatetruecolor($w, $h);
    $plank = (int)($h / 6);
    for ($p = 0; $p < 6; $p++) {
        $base = [mt_rand(92, 110), mt_rand(60, 72), mt_rand(38, 46)];
        imagefilledrectangle($im, 0, $p * $plank, $w, ($p + 1) * $plank, imagecolorallocate($im, ...$base));
        for ($g = 0; $g < 60; $g++) {
            $y = $p * $plank + mt_rand(0, $plank); $d = mt_rand(-18, 18);
            imageline($im, 0, $y, $w, $y + mt_rand(-6, 6), imagecolorallocatealpha($im, max(0, $base[0] + $d), max(0, $base[1] + $d), max(0, $base[2] + $d), 60));
        }
        imageline($im, 0, $p * $plank, $w, $p * $plank, imagecolorallocate($im, 50, 30, 20));
    }
    imagejpeg($im, $path, 84);
}
