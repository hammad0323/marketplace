<?php
/**
 * Router for PHP's built-in web server (local development only — Apache uses .htaccess):
 *   php -S localhost:8080 tools/dev-router.php
 */
$root = dirname(__DIR__);
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (preg_match('#^/(config|includes|database|docs|tools|logs|pages)(/|$)#', $path) || preg_match('#\.(sql|md|log)$#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
$file = $root . $path;
if ($path !== '/' && is_file($file)) {
    if (str_ends_with($file, '.php')) {
        chdir(dirname($file));
        $_SERVER['SCRIPT_FILENAME'] = $file;
        require $file;
        return true;
    }
    return false; // static asset
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    chdir($file);
    $_SERVER['SCRIPT_FILENAME'] = rtrim($file, '/') . '/index.php';
    require rtrim($file, '/') . '/index.php';
    return true;
}
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
