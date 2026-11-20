<?php
/**
 * Front controller for clean URLs:
 *   /api/v1/...                     REST API (api/index.php)
 *   /shop/{SHOP-ID}                 customer landing page reached from the QR code
 *   /shop/{SHOP-ID}/print           ordering flow
 *   /track, /track/{order}          order tracking
 *   /orders                         this device's orders
 *   /cv, /cv/{token}                CV generator
 *   /files/{kind}/{id}?e&d&s        signed temporary file links (private documents)
 *   /media/public/...               public images (logos, icons, template previews)
 *   /.well-known/...                Android App Links / iOS Universal Links
 * /admin/* and /owner/* are real folders served directly.
 */
require __DIR__ . '/core/bootstrap.php';

$uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$base = app_base_path();
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri = '/' . trim($uri, '/');
$seg = $uri === '/' ? [] : explode('/', trim($uri, '/'));

if (($seg[0] ?? '') === 'api') {
    require __DIR__ . '/api/index.php';
    exit;
}

send_security_headers();

// ---------------------------------------------------------------- signed private files
if (($seg[0] ?? '') === 'files' && count($seg) === 3 && ctype_digit($seg[2]) && preg_match('/^(order|upload|payment|cvphoto|preview-[a-f0-9]{32})$/', $seg[1])) {
    $kind = $seg[1];
    $id = (int) $seg[2];
    if (!signed_file_check($kind, $id, (int) ($_GET['e'] ?? 0), (string) ($_GET['d'] ?? ''), (string) ($_GET['s'] ?? ''))) {
        http_response_code(403);
        exit('This link has expired. Please open the file again from your order.');
    }
    $row = match ($kind) {
        'order' => db_row('SELECT storage_path, mime, original_name FROM order_files WHERE id = ? AND deleted_at IS NULL', [$id]),
        'upload' => db_row("SELECT COALESCE(IF(?, converted_path, NULL), storage_path) storage_path, IF(? AND converted_path IS NOT NULL, 'application/pdf', mime) mime, original_name FROM file_uploads WHERE id = ? AND status <> 'deleted'", [isset($_GET['pdf']) ? 1 : 0, isset($_GET['pdf']) ? 1 : 0, $id]),
        'cvphoto' => db_row("SELECT photo_path storage_path, 'image/jpeg' mime, 'photo.jpg' original_name FROM customer_cvs WHERE id = ? AND deleted_at IS NULL", [$id]),
        'payment' => db_row('SELECT proof_path storage_path, IF(proof_path LIKE "%.pdf", "application/pdf", "image/png") mime, CONCAT("payment-", id) original_name FROM subscription_payments WHERE id = ?', [$id]),
        default => str_starts_with($kind, 'preview-') ? ['storage_path' => 'previews/' . substr($kind, 8) . '.pdf', 'mime' => 'application/pdf', 'original_name' => 'print-preview.pdf'] : null,
    };
    $full = $row ? storage_file((string) $row['storage_path']) : null;
    if (!$full) {
        http_response_code(404);
        exit('File not available. Files are deleted automatically after the order is closed.');
    }
    serve_file($full, $row['mime'], $row['original_name'], (string) $_GET['d']);
}

// ---------------------------------------------------------------- public media
if (($seg[0] ?? '') === 'media' && ($seg[1] ?? '') === 'public') {
    $full = storage_file(implode('/', array_slice($seg, 1)));
    if (!$full || !preg_match('/\.(png|jpe?g|webp)$/i', $full)) {
        http_response_code(404);
        exit;
    }
    header('Cache-Control: public, max-age=604800');
    serve_file($full, (new finfo(FILEINFO_MIME_TYPE))->file($full), basename($full));
}

// ---------------------------------------------------------------- app links
if (($seg[0] ?? '') === '.well-known') {
    header('Content-Type: application/json');
    if (($seg[1] ?? '') === 'assetlinks.json') {
        $pkg = setting('android_package');
        $fps = array_values(array_filter(array_map('trim', explode(',', (string) setting('android_sha256')))));
        echo json_encode($pkg && $fps ? [['relation' => ['delegate_permission/common.handle_all_urls'], 'target' => ['namespace' => 'android_app', 'package_name' => $pkg, 'sha256_cert_fingerprints' => $fps]]] : [], JSON_UNESCAPED_SLASHES);
        exit;
    }
    if (($seg[1] ?? '') === 'apple-app-site-association') {
        $app = setting('ios_app_id');
        echo json_encode(['applinks' => ['apps' => [], 'details' => $app ? [['appID' => $app, 'paths' => ['/shop/*', '/track/*', '/cv/*'], 'components' => [['/' => '/shop/*'], ['/' => '/track/*'], ['/' => '/cv/*']]]] : []]], JSON_UNESCAPED_SLASHES);
        exit;
    }
    http_response_code(404);
    exit('{}');
}

if ($uri === '/manifest.webmanifest') {
    header('Content-Type: application/manifest+json');
    echo json_encode(['name' => platform_name(), 'short_name' => platform_name(), 'description' => 'Scan the shop QR, upload your files and collect your prints.',
        'start_url' => path('orders?source=pwa'), 'scope' => path(''), 'display' => 'standalone', 'background_color' => '#FFFFFF', 'theme_color' => '#0F766E',
        'icons' => [['src' => path('assets/img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png'], ['src' => path('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png'],
            ['src' => path('assets/img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable']]], JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------- customer pages
$page = null;
$params = [];
switch (true) {
    case $seg === []:
        $page = 'home';
        break;
    case $seg[0] === 'shop' && count($seg) === 2:
        $page = 'shop';
        $params['code'] = $seg[1];
        break;
    case $seg[0] === 'shop' && count($seg) === 3 && $seg[2] === 'print':
        $page = 'print';
        $params['code'] = $seg[1];
        break;
    case $seg[0] === 'track' && count($seg) <= 2:
        $page = 'track';
        $params['number'] = $seg[1] ?? null;
        break;
    case $seg[0] === 'orders' && count($seg) === 1:
        $page = 'orders';
        break;
    case $seg[0] === 'cv' && count($seg) === 1:
        $page = 'cv';
        break;
    case $seg[0] === 'cv' && count($seg) === 3 && $seg[1] === 'template-preview' && ctype_digit($seg[2]):
        $page = 'cv-template-preview';
        $params['id'] = (int) $seg[2];
        break;
    case $seg[0] === 'cv' && count($seg) >= 2 && preg_match('/^[a-f0-9]{32}$/', $seg[1]):
        $page = 'cv';
        $params['token'] = $seg[1];
        $params['view'] = $seg[2] ?? null;
        break;
    case $seg[0] === 'privacy' && count($seg) === 1:
        $page = 'privacy';
        break;
}
if (!$page) {
    http_response_code(404);
    $page = '404';
}
require __DIR__ . '/pages/' . $page . '.php';
