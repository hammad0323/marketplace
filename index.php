<?php
/**
 * Beglet storefront front controller.
 * Apache rewrites every non-file request here (see .htaccess); this maps
 * clean URLs to page controllers in app/pages.
 */

require __DIR__ . '/app/bootstrap.php';

$path = current_path();
$segments = $path === '' ? [] : explode('/', $path);
$GLOBALS['route_path'] = $path;

if ($path === 'index.php') {
    redirect(url(), 301);
}

// Machine-readable files served from the database.
switch ($path) {
    case 'sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo render_sitemap();
        exit;
    case 'robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo render_robots_txt();
        exit;
    case 'ads.txt':
        $ads = trim((string) setting('ads_txt', ''));
        if ($ads === '') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo "# ads.txt is not configured\n";
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo $ads . "\n";
        exit;
}

// Maintenance mode — admins can still browse the storefront.
$isPaymentCallback = ($segments[0] ?? '') === 'payment';
if (setting_bool('maintenance_mode') && !current_admin() && !$isPaymentCallback) {
    http_response_code(503);
    header('Retry-After: 3600');
    require APP_PATH . '/pages/maintenance.php';
    exit;
}

function route_page(string $page, array $params = []): void
{
    $GLOBALS['route'] = $params;
    require APP_PATH . '/pages/' . $page . '.php';
    exit;
}

function not_found(): void
{
    apply_redirects($GLOBALS['route_path'] ?? '');
    http_response_code(404);
    require APP_PATH . '/pages/404.php';
    exit;
}

$first = $segments[0] ?? '';
$count = count($segments);

switch (true) {
    case $path === '':
        route_page('home');
    case $path === 'shop':
        route_page('listing', ['mode' => 'shop']);
    case $path === 'search':
        route_page('listing', ['mode' => 'search']);
    case $first === 'category' && $count === 2:
        route_page('listing', ['mode' => 'category', 'slug' => $segments[1]]);
    case $first === 'collection' && $count === 2:
        route_page('listing', ['mode' => 'collection', 'slug' => $segments[1]]);
    case $first === 'product' && $count === 2:
        route_page('product', ['slug' => $segments[1]]);
    case $path === 'cart':
        route_page('cart');
    case $path === 'checkout':
        route_page('checkout');
    case $first === 'order' && $count === 2:
        route_page('order', ['number' => $segments[1]]);
    case $path === 'track-order':
        route_page('track-order');
    case $path === 'wishlist':
        route_page('wishlist');
    case $path === 'contact':
        route_page('contact');
    case $path === 'newsletter/unsubscribe':
        route_page('unsubscribe');
    case $first === 'account':
        $sub = $segments[1] ?? 'dashboard';
        $allowed = ['dashboard', 'login', 'register', 'logout', 'orders', 'addresses', 'profile', 'forgot-password', 'reset-password'];
        if (!in_array($sub, $allowed, true) || $count > 3 || ($count === 3 && $sub !== 'orders')) {
            not_found();
        }
        route_page('account/' . $sub, ['id' => $segments[2] ?? null]);
    case $first === 'payment':
        route_page('payment', ['segments' => array_slice($segments, 1)]);
    case $first === 'api' && $count === 2:
        $endpoints = ['cart', 'wishlist', 'search', 'quick-view', 'newsletter', 'checkout-quote', 'review'];
        if (!in_array($segments[1], $endpoints, true)) {
            json_response(['ok' => false, 'message' => 'Not found'], 404);
        }
        require APP_PATH . '/ajax/' . $segments[1] . '.php';
        exit;
}

// CMS pages: /about-us, /privacy-policy …
if ($count === 1 && preg_match('/^[a-z0-9-]+$/', $first)) {
    $page = db_one('SELECT * FROM pages WHERE slug = ? AND is_published = 1', [$first]);
    if ($page) {
        route_page('page', ['page' => $page]);
    }
}

not_found();
