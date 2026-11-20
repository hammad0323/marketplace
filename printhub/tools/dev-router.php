<?php
// Router for PHP's built-in server: php -S localhost:8090 tools/dev-router.php
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(config|core|database|storage|vendor|cron|tools|mobile|docs|pages|api/modules)(/|$)#', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
$file = $root . $path;
if ($path !== '/' && is_file($file) && !str_starts_with($path, '/.well-known')) {
    if (str_ends_with($file, '.php')) {
        chdir(dirname($file));
        require $file;
        return true;
    }
    return false;
}
if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
    chdir($file);
    require rtrim($file, '/') . '/index.php';
    return true;
}
chdir($root);
require $root . '/index.php';
