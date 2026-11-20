<?php
/**
 * QR codes (chillerlan/php-qrcode) and the printable counter poster (dompdf).
 * The shop QR always encodes APP_URL/shop/{SHOP-ID}; "regenerate" only changes the design and version
 * number, never the link — printed posters keep working.
 */

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;

function hex_rgb(string $hex): array
{
    $n = hexdec(ltrim($hex, '#'));
    return [($n >> 16) & 255, ($n >> 8) & 255, $n & 255];
}

/** PNG bytes. $o: fg, bg, accent (finder patterns), logo (absolute path or null), scale. */
function qr_png(string $data, array $o = []): string
{
    $fg = hex_rgb($o['fg'] ?? '#0F172A');
    $bg = hex_rgb($o['bg'] ?? '#FFFFFF');
    $accent = hex_rgb($o['accent'] ?? ($o['fg'] ?? '#0F172A'));
    $logo = $o['logo'] ?? null;
    $dark = [QRMatrix::M_DARKMODULE, QRMatrix::M_DATA_DARK, QRMatrix::M_ALIGNMENT_DARK, QRMatrix::M_TIMING_DARK, QRMatrix::M_FORMAT_DARK, QRMatrix::M_VERSION_DARK, QRMatrix::M_QUIETZONE_DARK, QRMatrix::M_SEPARATOR_DARK, QRMatrix::M_LOGO_DARK];
    $light = [QRMatrix::M_DATA, QRMatrix::M_FINDER, QRMatrix::M_SEPARATOR, QRMatrix::M_ALIGNMENT, QRMatrix::M_TIMING, QRMatrix::M_FORMAT, QRMatrix::M_VERSION, QRMatrix::M_QUIETZONE, QRMatrix::M_LOGO, QRMatrix::M_DARKMODULE_LIGHT, QRMatrix::M_FINDER_DOT_LIGHT];
    $values = [];
    foreach ($dark as $m) {
        $values[$m] = $fg;
    }
    foreach ($light as $m) {
        $values[$m] = $bg;
    }
    $values[QRMatrix::M_FINDER_DARK] = $accent;
    $values[QRMatrix::M_FINDER_DOT] = $accent;
    $options = new QROptions([
        'outputType' => QROutputInterface::GDIMAGE_PNG, 'eccLevel' => EccLevel::H, 'scale' => (int) ($o['scale'] ?? 16), 'outputBase64' => false,
        'returnResource' => true, 'bgColor' => $bg, 'moduleValues' => $values, 'addLogoSpace' => (bool) $logo, 'logoSpaceWidth' => 15, 'logoSpaceHeight' => 15,
        'drawCircularModules' => !empty($o['rounded']), 'circleRadius' => 0.42,
        'keepAsSquare' => [QRMatrix::M_FINDER_DARK, QRMatrix::M_FINDER_DOT, QRMatrix::M_ALIGNMENT_DARK],
    ]);
    $qr = new QRCode($options);
    $qr->addByteSegment($data);
    $img = $qr->renderMatrix($qr->getQRMatrix());
    if ($logo && is_file($logo) && ($l = @imagecreatefromstring((string) file_get_contents($logo)))) {
        $w = imagesx($img);
        $box = (int) round($w * 0.2);
        $pad = (int) round($box * 0.1);
        $x = (int) (($w - $box) / 2);
        imagefilledrectangle($img, $x, $x, $x + $box, $x + $box, imagecolorallocate($img, $bg[0], $bg[1], $bg[2]));
        $lw = imagesx($l);
        $lh = imagesy($l);
        $s = ($box - 2 * $pad) / max($lw, $lh);
        imagecopyresampled($img, $l, $x + $pad + (int) (($box - 2 * $pad - $lw * $s) / 2), $x + $pad + (int) (($box - 2 * $pad - $lh * $s) / 2), 0, 0, (int) ($lw * $s), (int) ($lh * $s), $lw, $lh);
        imagedestroy($l);
    }
    ob_start();
    imagepng($img, null, 6);
    imagedestroy($img);
    return (string) ob_get_clean();
}

/** The shop ordering QR with the owner's saved design. */
function shop_qr_png(array $shop, int $scale = 16): string
{
    $q = shop_qr_settings((int) $shop['id']);
    $logo = (int) $q['show_logo'] && $shop['logo_path'] ? storage_file($shop['logo_path']) : null;
    return qr_png(shop_url($shop), ['fg' => $q['fg_color'], 'bg' => $q['bg_color'], 'accent' => $q['accent_color'], 'logo' => $logo, 'scale' => $scale]);
}

/** Wi-Fi QR (separate from the ordering QR). Null when no network is configured. */
function shop_wifi_qr_png(array $shop): ?string
{
    $w = shop_wifi((int) $shop['id'], true);
    if (!$w['ssid']) {
        return null;
    }
    return qr_png(wifi_qr_payload($w['ssid'], (string) $w['password'], $w['security'], (bool) $w['is_hidden_network']), ['scale' => 12]);
}

/** Printable A4 counter poster. With $withWifi the Wi-Fi QR is added (only if public sharing is on). */
function shop_qr_poster_pdf(array $shop, bool $withWifi = true): string
{
    $q = shop_qr_settings((int) $shop['id']);
    $qr = base64_encode(shop_qr_png($shop, 20));
    $wifi = $withWifi ? shop_public_wifi((int) $shop['id']) : null;
    $wifiQr = $wifi ? base64_encode(qr_png($wifi['qr_payload'], ['scale' => 10])) : null;
    $accent = e($q['accent_color']);
    $logo = $shop['logo_path'] && ($f = storage_file($shop['logo_path'])) ? base64_encode((string) file_get_contents($f)) : null;
    $html = '<html><head><meta charset="utf-8"><style>
        @page { margin: 0; } body { font-family: DejaVu Sans, sans-serif; margin: 0; color: #0f172a; text-align: center; }
        .top { background: ' . $accent . '; color: #fff; padding: 34px 40px 28px; }
        .top h1 { font-size: 34px; margin: 0 0 6px; } .top p { font-size: 15px; margin: 0; opacity: .9; }
        .logo { height: 70px; margin-bottom: 10px; }
        .qr { margin: 34px auto 10px; width: 330px; height: 330px; padding: 14px; border: 3px solid ' . $accent . '; border-radius: 18px; }
        .qr img { width: 330px; height: 330px; }
        .steps { font-size: 14px; color: #334155; margin: 6px 60px; } .url { font-size: 13px; color: #475569; margin-top: 10px; }
        .code { display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 6px; background: #f1f5f9; font-size: 14px; letter-spacing: 2px; }
        .wifi { margin: 26px 60px 0; border: 1px dashed #94a3b8; border-radius: 14px; padding: 16px; text-align: left; }
        .wifi img { width: 120px; height: 120px; float: right; } .wifi h3 { margin: 0 0 6px; font-size: 18px; } .wifi p { margin: 3px 0; font-size: 14px; }
        .foot { position: absolute; bottom: 22px; left: 0; right: 0; font-size: 11px; color: #94a3b8; }
    </style></head><body>
    <div class="top">' . ($logo ? '<img class="logo" src="data:image/png;base64,' . $logo . '">' : '') . '<h1>' . e($q['heading']) . '</h1><p>' . e($shop['name']) . ($q['subheading'] ? ' · ' . e($q['subheading']) : '') . '</p></div>
    <div class="qr"><img src="data:image/png;base64,' . $qr . '"></div>
    <p class="steps"><b>1.</b> Open your phone camera &nbsp; <b>2.</b> Scan the code &nbsp; <b>3.</b> Upload your files &amp; collect your prints</p>
    <p class="url">' . e(shop_url($shop)) . '</p><span class="code">' . e($shop['shop_code']) . '</span>'
    . ($wifi ? '<div class="wifi"><img src="data:image/png;base64,' . $wifiQr . '"><h3>Connect to our Wi-Fi</h3><p>Network: <b>' . e($wifi['ssid']) . '</b></p>'
        . ($wifi['password'] !== '' ? '<p>Password: <b>' . e($wifi['password']) . '</b></p>' : '<p>Open network</p>') . '<p style="color:#64748b;font-size:12px">Scan this code to join the Wi-Fi on most phones.</p></div>' : '')
    . '<div class="foot">' . e($shop['address'] . ', ' . $shop['city']) . ' · ' . e($shop['phone']) . ' · Powered by ' . e(platform_name()) . '</div>
    </body></html>';
    $opt = new Options();
    $opt->set('isRemoteEnabled', false);
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->setChroot([PH_ROOT]);
    $pdf = new Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4');
    $pdf->render();
    return (string) $pdf->output();
}
