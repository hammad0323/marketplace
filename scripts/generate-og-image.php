<?php
/**
 * Regenerates assets/img/og-default.png — the fallback social-share image
 * for every page that doesn't set its own $ogImage. Facebook/WhatsApp/
 * LinkedIn/X don't reliably render SVG for og:image (some show no preview
 * at all), so this must stay a real PNG. Also fixes the old og-default.svg,
 * which had the pre-rebrand name ("MediConnect") and a mismatched purple
 * gradient baked into the graphic itself instead of the real brand colors.
 *
 * Re-run this after a rebrand (new site name/tagline) to refresh the image:
 *   php scripts/generate-og-image.php
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../config/config.php';

$width = 1200;
$height = 630;
$img = imagecreatetruecolor($width, $height);

// Brand gradient, matching --gradient-primary in assets/css/style.css
// (#0C6B5D -> #22C3AB -> #C4EEE7), rendered as horizontal bands since GD
// has no native linear-gradient fill.
$stops = [[12, 107, 93], [34, 195, 171], [196, 238, 231]];
for ($x = 0; $x < $width; $x++) {
    $t = $x / $width;
    $segment = min(1, $t * 2);
    if ($t < 0.5) {
        $c1 = $stops[0];
        $c2 = $stops[1];
        $localT = $t / 0.5;
    } else {
        $c1 = $stops[1];
        $c2 = $stops[2];
        $localT = ($t - 0.5) / 0.5;
    }
    $r = (int) round($c1[0] + ($c2[0] - $c1[0]) * $localT);
    $g = (int) round($c1[1] + ($c2[1] - $c1[1]) * $localT);
    $b = (int) round($c1[2] + ($c2[2] - $c1[2]) * $localT);
    $color = imagecolorallocate($img, $r, $g, $b);
    imageline($img, $x, 0, $x, $height, $color);
}

$white = imagecolorallocatealpha($img, 255, 255, 255, 0);
$whiteFaint = imagecolorallocatealpha($img, 255, 255, 255, 100);
imagefilledellipse($img, 1020, 120, 360, 360, $whiteFaint);
imagefilledellipse($img, 120, 540, 280, 280, $whiteFaint);

$siteName = get_setting('site_name', SITE_NAME);
$tagline = get_setting('site_tagline', 'Trusted care, one click away');

$fontBold = null;
$fontRegular = null;
foreach ([
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
] as $candidate) {
    if (is_file($candidate)) { $fontBold = $candidate; break; }
}
foreach ([
    '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
] as $candidate) {
    if (is_file($candidate)) { $fontRegular = $candidate; break; }
}

if ($fontBold && $fontRegular) {
    $titleSize = 58;
    $box = imagettfbbox($titleSize, 0, $fontBold, $siteName);
    $titleWidth = abs($box[4] - $box[0]);
    imagettftext($img, $titleSize, 0, (int) (($width - $titleWidth) / 2), 390, $white, $fontBold, $siteName);

    $tagSize = 26;
    $box = imagettfbbox($tagSize, 0, $fontRegular, $tagline);
    $tagWidth = abs($box[4] - $box[0]);
    imagettftext($img, $tagSize, 0, (int) (($width - $tagWidth) / 2), 440, $white, $fontRegular, $tagline);
} else {
    // No TTF available on this server — fall back to GD's built-in bitmap
    // font so the script still produces a usable (if plainer) image.
    $font = 5;
    imagestring($img, $font, (int) (($width - strlen($siteName) * imagefontwidth($font)) / 2), 370, $siteName, $white);
    imagestring($img, 3, (int) (($width - strlen($tagline) * imagefontwidth(3)) / 2), 400, $tagline, $white);
}

// A simple heart/pulse mark above the wordmark, echoing the site's brand icon.
imagefilledellipse($img, 578, 255, 38, 38, $white);
imagefilledellipse($img, 622, 255, 38, 38, $white);
imagefilledpolygon($img, [557, 263, 600, 305, 643, 263], 3, $white);

$outPath = __DIR__ . '/../assets/img/og-default.png';
imagepng($img, $outPath, 6);
imagedestroy($img);

echo "Wrote $outPath (" . filesize($outPath) . " bytes)\n";
