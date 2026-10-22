<?php
/**
 * Front controller. .htaccess sends every request that is not a real file here;
 * the path is matched against the routes below and the matching page in /pages is loaded.
 */

define('UC_ROOT', __DIR__);
require __DIR__ . '/includes/bootstrap.php';

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}
$path = '/' . trim(rawurldecode($path), '/');
$seg = array_values(array_filter(explode('/', $path), 'strlen'));
$ROUTE = ['path' => $path, 'seg' => $seg, 'params' => []];

// Trailing-slash and index.php normalisation (canonical URLs)
$rawPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/' && str_ends_with($rawPath, '/') && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . url(ltrim($path, '/')) . ($qs ? '?' . $qs : ''), true, 301);
    exit;
}

/** Shows the 404 page after checking the redirects table. */
function route_not_found(string $path): never
{
    $r = db_row('SELECT * FROM redirects WHERE from_path = ? AND is_active = 1', [$path]);
    if ($r) {
        db_exec('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [(int) $r['id']]);
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        $to = preg_match('#^https?://#', $r['to_path']) ? $r['to_path'] : url(ltrim($r['to_path'], '/'));
        header('Location: ' . $to . ($qs && !str_contains($to, '?') ? '?' . $qs : ''), true, in_array((int) $r['status_code'], [301, 302, 307, 308], true) ? (int) $r['status_code'] : 301);
        exit;
    }
    http_response_code(404);
    require UC_ROOT . '/pages/404.php';
    exit;
}

$page = null;
$s0 = $seg[0] ?? '';
$n = count($seg);

switch (true) {
    case $n === 0:
        $page = 'home';
        break;
    case $n === 1 && in_array($s0, ['shop', 'new-arrivals', 'sale', 'search'], true):
        $ROUTE['params']['mode'] = $s0;
        $page = 'listing';
        break;
    case $n === 2 && $s0 === 'category':
        $ROUTE['params'] = ['mode' => 'category', 'slug' => $seg[1]];
        $page = 'listing';
        break;
    case $n === 1 && $s0 === 'collections':
        $page = 'collections';
        break;
    case $n === 2 && $s0 === 'collections':
        $ROUTE['params'] = ['mode' => 'collection', 'slug' => $seg[1]];
        $page = 'listing';
        break;
    case $n === 2 && $s0 === 'product':
        $ROUTE['params']['slug'] = $seg[1];
        $page = 'product';
        break;
    case $n === 1 && $s0 === 'cart':
        $page = 'cart';
        break;
    case $n === 1 && $s0 === 'checkout':
        $page = 'checkout';
        break;
    case $n === 3 && $s0 === 'checkout' && $seg[1] === 'pay':
        $ROUTE['params']['number'] = $seg[2];
        $page = 'checkout-pay';
        break;
    case $n === 2 && $s0 === 'order':
        $ROUTE['params']['number'] = $seg[1];
        $page = 'order';
        break;
    case $n === 1 && $s0 === 'track-order':
        $page = 'track-order';
        break;
    case $n === 1 && $s0 === 'wishlist':
        $page = 'wishlist';
        break;
    case $s0 === 'account':
        $sub = $seg[1] ?? 'dashboard';
        $allowed = ['login', 'register', 'logout', 'forgot-password', 'reset-password', 'dashboard', 'orders', 'addresses', 'profile'];
        if (!in_array($sub, $allowed, true) || $n > 3 || ($n === 3 && $sub !== 'orders')) {
            route_not_found($path);
        }
        $ROUTE['params']['number'] = $seg[2] ?? null;
        $page = 'account/' . $sub;
        break;
    case $n === 1 && $s0 === 'contact':
        $page = 'contact';
        break;
    case $n === 2 && $s0 === 'newsletter' && $seg[1] === 'unsubscribe':
        $page = 'unsubscribe';
        break;
    case $n === 1 && $s0 === 'sitemap.xml':
        $page = 'sitemap';
        break;
    case $n === 1 && in_array($s0, ['robots.txt', 'ads.txt'], true):
        $ROUTE['params']['file'] = $s0;
        $page = 'textfile';
        break;
    case $n === 1 && preg_match('/^[a-z0-9\-]+$/', $s0):
        $ROUTE['params']['slug'] = $s0;
        $page = 'page';
        break;
}

if (!$page || !is_file(UC_ROOT . '/pages/' . $page . '.php')) {
    route_not_found($path);
}
require UC_ROOT . '/pages/' . $page . '.php';
