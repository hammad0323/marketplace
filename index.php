<?php
/**
 * Fahim Store - front controller.
 * Apache rewrites every clean URL to this file; we map the path to a page
 * script in /pages (which customers never see).
 */
require __DIR__ . '/includes/bootstrap.php';

$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$bp = base_path();
if ($bp !== '' && strpos($uri, $bp) === 0) {
    $uri = substr($uri, strlen($bp));
}
// Never expose /index.php
if (preg_match('#^/index\.php(/|$)#', $uri)) {
    $rest = substr($uri, 10);
    redirect(url(ltrim($rest, '/')) . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''), 301);
}
$path = trim($uri, '/');
$seg = $path === '' ? [] : explode('/', $path);
$GLOBALS['ROUTE'] = $seg;

/** Render a page script. */
function route_page(string $page, array $params = []): void
{
    $GLOBALS['ROUTE_PARAMS'] = $params;
    require FS_ROOT . '/pages/' . $page . '.php';
    exit;
}

function route_404(): void
{
    http_response_code(404);
    require FS_ROOT . '/pages/404.php';
    exit;
}

/** Canonical trailing slash for HTML pages (301). */
function route_trailing_slash(string $uri): void
{
    if ($uri !== '/' && substr($uri, -1) !== '/' && !preg_match('/\.[a-z0-9]{2,5}$/i', $uri) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        redirect(url(ltrim($uri, '/') . '/') . ($qs !== '' ? '?' . $qs : ''), 301);
    }
}

$first = $seg[0] ?? '';

// Machine endpoints (no trailing slash rules)
if ($first === 'api') {
    $name = preg_replace('/[^a-z\-]/', '', $seg[1] ?? '');
    $file = FS_ROOT . '/api/' . $name . '.php';
    if ($name !== '' && is_file($file)) {
        require $file;
        exit;
    }
    json_error('Not found', 404);
}
if ($path === 'sitemap.xml') route_page('sitemap');
if ($path === 'robots.txt') route_page('robots');

// Redirect table (old slugs -> new) before anything else
$redir = db_row('SELECT id, new_path, status_code FROM redirects WHERE old_path = ? OR old_path = ?', ['/' . $path . '/', '/' . $path]);
if ($redir) {
    db_exec('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$redir['id']]);
    redirect(url(ltrim($redir['new_path'], '/')), (int) $redir['status_code'] ?: 301);
}

route_trailing_slash($uri);

switch ($first) {
    case '':
        route_page('home');
    case 'shop':
    case 'search':
        route_page('shop', ['mode' => $first]);
    case 'category':
        if (count($seg) < 2) redirect('shop/', 301);
        route_page('category', ['slugs' => array_slice($seg, 1)]);
    case 'product':
        if (empty($seg[1])) redirect('shop/', 301);
        route_page('product', ['slug' => $seg[1]]);
    case 'brand':
        if (empty($seg[1])) route_page('brands');
        route_page('brand', ['slug' => $seg[1]]);
    case 'brands':
        route_page('brands');
    case 'offers':
        route_page(empty($seg[1]) ? 'offers' : 'offer', ['slug' => $seg[1] ?? '']);
    case 'monthly-rashan':
        route_page('rashan');
    case 'cart':
        route_page('cart');
    case 'checkout':
        route_page('checkout');
    case 'order-success':
        route_page('order-success', ['number' => $seg[1] ?? '']);
    case 'track-order':
        route_page('track-order');
    case 'login':
        route_page('account/login');
    case 'register':
        route_page('account/register');
    case 'logout':
        route_page('account/logout');
    case 'wishlist':
        route_page('account/wishlist');
    case 'my-account':
        $sub = $seg[1] ?? 'dashboard';
        $allowed = ['dashboard', 'orders', 'order', 'addresses', 'wishlist', 'rashan-lists', 'profile', 'password', 'reorder'];
        if (!in_array($sub, $allowed, true)) route_404();
        route_page('account/' . $sub, ['id' => $seg[2] ?? '']);
    case 'contact':
        route_page('contact');
    case 'page':
        if (empty($seg[1])) route_404();
        route_page('page', ['slug' => $seg[1]]);
}

// Single-segment: CMS page slug (e.g. /about-us/) or root category shortcut (/books/...)
if (count($seg) === 1 && db_val('SELECT id FROM pages WHERE slug = ? AND is_active = 1', [$first])) {
    route_page('page', ['slug' => $first]);
}
$cat = category_by_slug($first);
if ($cat && $cat['parent_id'] === null) {
    redirect('category/' . implode('/', $seg) . '/', 301);
}
route_404();
