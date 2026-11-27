<?php
/**
 * The Hashir — front controller.
 * Every request that is not a real file is routed here by .htaccess.
 * Existing public URLs are preserved (see the route table below).
 */

// Built-in PHP server (local testing): serve real files directly.
if (PHP_SAPI === 'cli-server') {
    // Local testing only: let the built-in server deliver real static files (assets, images).
    $devFile = $_SERVER['DOCUMENT_ROOT'] . rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (is_file($devFile) && !preg_match('/\.(php|sql|md)$/i', $devFile)) {
        return false;
    }
}

require __DIR__ . '/includes/bootstrap.php';

$path = rtrim(current_path(), '/');
if ($path === '') {
    $path = '/';
}
$seg = array_values(array_filter(explode('/', $path), 'strlen'));
$ROUTE = array();

function route_page($file, $params = array())
{
    global $ROUTE;
    $ROUTE = $params;
    require __DIR__ . '/pages/' . $file . '.php';
    exit;
}

$first = isset($seg[0]) ? $seg[0] : '';
$n = count($seg);

// A published static page (Super Admin → Pages) can replace a coded page with the same slug, e.g. /about
if ($n === 1 && in_array($first, array('about', 'support', 'solution-mosque'), true)) {
    $pg = page_doc($first);
    if ($pg && $pg['status'] === 'published' && empty($pg['source'])) {
        route_page('static', array('slug' => $first));
    }
}

switch (true) {
    case $first === ltrim(ADMIN_PATH, '/'):
        $BO_SEG = array_slice($seg, 1);
        require __DIR__ . '/app_admin/index.php';
        exit;
    case $first === ltrim(PORTAL_PATH, '/'):
        $BO_SEG = array_slice($seg, 1);
        require __DIR__ . '/app_portal/index.php';
        exit;
    case $path === '/':
        route_page('home');
    case $path === '/index' || $path === '/index.php':
        redirect('/');
    case $path === '/mosques':
        route_page('venues', array('role' => 1));
    case $first === 'mosques-details' && $n === 3 && ctype_digit($seg[2]):
        route_page('venue', array('id' => (int)$seg[2], 'slug' => $seg[1], 'kind' => 'mosque'));
    case $first === 'organization-details' && $n === 3 && ctype_digit($seg[2]):
        route_page('venue', array('id' => (int)$seg[2], 'slug' => $seg[1], 'kind' => 'org'));
    case $path === '/organizations-catalog':
        route_page('org_catalog');
    case $first === 'organizations' && $n === 3 && ctype_digit($seg[2]):
        route_page('venues', array('role' => (int)$seg[2], 'slug' => $seg[1]));
    case $path === '/organizations':
        redirect('/organizations-catalog');
    case $path === '/program-catalog':
        route_page('catalog', array('gc' => 2));
    case $path === '/services-catalog':
        route_page('catalog', array('gc' => 3));
    case $path === '/events-catalog':
        route_page('catalog', array('gc' => 1));
    case in_array($first, array('programs', 'services', 'events'), true) && $n === 3 && ctype_digit($seg[2]):
        route_page('category', array('id' => (int)$seg[2], 'slug' => $seg[1], 'type' => $first));
    case $path === '/programs':
        redirect('/program-catalog');
    case $path === '/services':
        redirect('/services-catalog');
    case $path === '/events':
        redirect('/events-catalog');
    case $first === 'details' && $n === 3 && ctype_digit($seg[2]):
        route_page('detail', array('id' => (int)$seg[2], 'slug' => $seg[1]));
    case $first === 'details-list' && $n === 4 && ctype_digit($seg[3]):
        route_page('venue_list', array('id' => (int)$seg[3], 'type' => strtolower($seg[2])));
    case $path === '/prayer-times':
        route_page('prayer_index');
    case $first === 'prayer-times' && $n === 4 && ctype_digit($seg[3]):
        route_page('prayer_city', array('id' => (int)$seg[3], 'slug' => $seg[2]));
    case $path === '/blogs':
        route_page('blogs');
    case $first === 'blog' && $n === 3 && ctype_digit($seg[2]):
        route_page('blog', array('id' => (int)$seg[2], 'slug' => $seg[1]));
    case $first === 'blog-preview' && $n === 2:
        route_page('blog', array('preview' => true, 'key' => $seg[1]));
    case $first === 'page-preview' && $n === 2:
        route_page('static', array('slug' => $seg[1], 'preview' => true));
    case $path === '/mosque-map':
        route_page('map');
    case $path === '/search':
        route_page('search');
    case $path === '/about':
        route_page('about');
    case $path === '/support':
        route_page('support');
    case $path === '/solution-mosque':
        route_page('solutions');
    case $path === '/contact':
        redirect('/support#contact');
    case $n === 1 && page_slug_ok($first) && page_doc($first) !== null:
        route_page('static', array('slug' => $first));
    case $path === '/setup-check':
        route_page('setup_check');
    case $path === '/sitemap.xml':
        route_page('sitemap');
    case $path === '/robots.txt':
        route_page('robots');
    case $path === '/api/cities':
        require __DIR__ . '/api/cities.php';
        exit;
    case $path === '/api/contact':
        require __DIR__ . '/api/contact.php';
        exit;
    case $path === '/api/follow':
        require __DIR__ . '/api/follow.php';
        exit;
    case $path === '/api/newsletter':
        require __DIR__ . '/api/newsletter.php';
        exit;
    case $path === '/api/map':
        require __DIR__ . '/api/map.php';
        exit;
    default:
        route_page('404');
}
