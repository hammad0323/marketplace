<?php
/**
 * Local development only:  php -S localhost:8000 dev-router.php
 * Mirrors the .htaccess rules so clean URLs work without Apache.
 * (Not used on Apache hosting — you can delete it on the live server.)
 */
$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = __DIR__;

if (preg_match('~/(includes|logs)(/|$)|^/install/.+\.sql$|/\.~', $uri)) {
    http_response_code(403);
    exit('Forbidden');
}
if ($uri !== '/' && is_file($root . $uri) && !preg_match('~\.php$~', $uri)) {
    return false; // static file
}

$routes = [
    '~^/product/([a-z0-9-]+)/?$~i'                 => ['product.php', ['slug']],
    '~^/category/([a-z0-9-]+)/([a-z0-9-]+)/?$~i'   => ['shop.php', ['cat', 'sub']],
    '~^/category/([a-z0-9-]+)/?$~i'                => ['shop.php', ['cat']],
    '~^/page/([a-z0-9-]+)/?$~i'                    => ['page.php', ['slug']],
    '~^/order-success/([A-Z0-9]+)/?$~i'            => ['order-success.php', ['no']],
    '~^/pay/([A-Z0-9]+)/?$~i'                      => ['pay.php', ['no']],
    '~^/robots\.txt$~'                             => ['seo-files.php', [], ['f' => 'robots']],
    '~^/ads\.txt$~'                                => ['seo-files.php', [], ['f' => 'ads']],
    '~^/sitemap\.xml$~'                            => ['seo-files.php', [], ['f' => 'sitemap']],
];
$file = null;
foreach ($routes as $re => $r) {
    if (preg_match($re, $uri, $m)) {
        $file = $r[0];
        foreach ($r[1] as $i => $k) $_GET[$k] = $m[$i + 1];
        foreach ($r[2] ?? [] as $k => $v) $_GET[$k] = $v;
        break;
    }
}
if ($file === null) {
    $path = rtrim($uri, '/');
    if ($path === '') $file = 'index.php';
    elseif (is_dir($root . $path) && is_file($root . $path . '/index.php')) $file = ltrim($path, '/') . '/index.php';
    elseif (preg_match('~\.php$~', $path) && is_file($root . $path)) $file = ltrim($path, '/');
    elseif (is_file($root . $path . '.php')) $file = ltrim($path, '/') . '.php';
    else { $file = '404.php'; http_response_code(404); }
}
$_REQUEST = array_merge($_GET, $_POST);
$_SERVER['SCRIPT_FILENAME'] = $root . '/' . $file;
$_SERVER['SCRIPT_NAME'] = '/' . $file;
$_SERVER['PHP_SELF'] = '/' . $file;
chdir(dirname($root . '/' . $file));
require $root . '/' . $file;
