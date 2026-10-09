<?php
/**
 * Storefront front controller. .htaccess sends every request that is not a
 * real file to this script; we map the clean path to a page file.
 */
require __DIR__ . '/includes/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

$path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
    $path = substr($path, strlen(BASE_PATH));
}
$path = '/' . trim(rawurldecode($path), '/');
$seg = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
$GLOBALS['route_path'] = $path;

function render_page(string $__view, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require ROOT_PATH . '/pages/' . $__view . '.php';
    exit;
}

function not_found(): void
{
    http_response_code(404);
    render_page('404');
}

$s0 = $seg[0] ?? '';
$s1 = $seg[1] ?? null;
$s2 = $seg[2] ?? null;

switch (true) {
    case $path === '/':
        render_page('home');
    case $s0 === 'shop' && !$s1:
        render_page('listing', ['mode' => 'shop']);
    case $s0 === 'new-arrivals' && !$s1:
        render_page('listing', ['mode' => 'new']);
    case $s0 === 'best-sellers' && !$s1:
        render_page('listing', ['mode' => 'best']);
    case $s0 === 'search' && !$s1:
        render_page('listing', ['mode' => 'search']);
    case $s0 === 'category' && $s1 && !$s2:
        render_page('listing', ['mode' => 'category', 'slug' => $s1]);
    case $s0 === 'collections' && !$s1:
        render_page('collections');
    case $s0 === 'collections' && $s1 && !$s2:
        render_page('listing', ['mode' => 'collection', 'slug' => $s1]);
    case $s0 === 'product' && $s1 && !$s2:
        render_page('product', ['slug' => $s1]);
    case $s0 === 'cart' && !$s1:
        render_page('cart');
    case $s0 === 'checkout' && !$s1:
        render_page('checkout');
    case $s0 === 'order' && $s1 && !$s2:
        render_page('order', ['number' => $s1]);
    case $s0 === 'track-order' && !$s1:
        render_page('track-order');
    case $s0 === 'wishlist' && !$s1:
        render_page('wishlist');
    case $s0 === 'contact' && !$s1:
        render_page('contact');
    case $s0 === 'newsletter' && $s1 === 'unsubscribe':
        render_page('unsubscribe');
    case $s0 === 'account':
        $sub = $s1 ?? 'dashboard';
        $allowed = ['dashboard', 'login', 'register', 'logout', 'forgot-password', 'reset-password', 'orders', 'addresses', 'profile'];
        if (!in_array($sub, $allowed, true)) not_found();
        render_page('account', ['sub' => $sub, 'param' => $s2]);
    case $s0 === 'ajax' && $s1 && !$s2:
        $file = ROOT_PATH . '/ajax/' . preg_replace('/[^a-z0-9\-]/', '', $s1) . '.php';
        if (!is_file($file)) json_out(['ok' => false, 'message' => 'Not found'], 404);
        require $file;
        exit;
    case $s0 === 'payment' && $s1 && $s2:
        render_page('payment', ['gateway' => $s1, 'action' => $s2, 'param' => $seg[3] ?? null]);
    case $path === '/sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo sitemap_xml();
        exit;
    case $path === '/robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo robots_txt();
        exit;
    case $path === '/ads.txt':
        $ads = trim((string)setting('ads_txt'));
        if ($ads === '') not_found();
        header('Content-Type: text/plain; charset=utf-8');
        echo $ads . "\n";
        exit;
}

// Manual/automatic redirects (old slugs, legacy URLs).
if ($r = redirect_lookup($path)) {
    redirect(preg_match('#^https?://#', $r['to_path']) ? $r['to_path'] : url($r['to_path']), (int)$r['status_code'] === 302 ? 302 : 301);
}

// Content pages: /about-ebaya, /size-guide, ...
if (count($seg) === 1 && v_slug($s0)) {
    $page = db_one("SELECT * FROM pages WHERE slug = ?" . (admin_id() ? '' : " AND status = 'published'"), [$s0]);
    if ($page) render_page('page', ['page' => $page]);
}

not_found();
