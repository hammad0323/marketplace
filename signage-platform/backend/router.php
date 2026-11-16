<?php
/**
 * Router for PHP's built-in development server only:
 *     php -S 0.0.0.0:8080 -t backend backend/router.php
 * Mirrors the .htaccess rules (clean URLs, blocked internals).
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rawurldecode($path);
if (preg_match('#^/(config|includes|database|cron|logs)(/|$)#', $path) || preg_match('#(^|/)(_[^/]*\.php|\.[^/]+)$#', $path)) {
    http_response_code(403); echo 'Forbidden'; return true;
}
if (preg_match('#^/uploads/#', $path) && !preg_match('#^/uploads/images/avatars/#', $path)) {
    http_response_code(403); echo 'Forbidden'; return true;
}
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    if (str_ends_with($file, '.php')) { $_SERVER['SCRIPT_NAME'] = $path; chdir(dirname($file)); require $file; return true; }
    return false;   // static file
}
if (is_file($file . '.php')) { $_SERVER['SCRIPT_NAME'] = $path . '.php'; chdir(dirname($file . '.php')); require $file . '.php'; return true; }
if ($path === '/' || $path === '') { require __DIR__ . '/index.php'; return true; }
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['status' => false, 'message' => 'Endpoint not found', 'data' => null, 'code' => 'NOT_FOUND']);
return true;
