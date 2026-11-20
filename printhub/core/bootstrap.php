<?php
/** Loads configuration, libraries and the core modules. Every entry point requires this file. */

define('PH_ROOT', dirname(__DIR__));

if (!is_file(PH_ROOT . '/config/config.php')) {
    http_response_code(503);
    exit('PrintHub is not configured yet. Copy config/config.sample.php to config/config.php — see docs/INSTALL.md.');
}
require PH_ROOT . '/config/config.php';
require PH_ROOT . '/vendor/autoload.php';

date_default_timezone_set(APP_TIMEZONE);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);
mb_internal_encoding('UTF-8');

set_exception_handler(function (Throwable $e) {
    error_log('[PrintHub] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $api = str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
    if ($api) {
        header('Content-Type: application/json');
        echo json_encode(['status' => false, 'message' => 'Something went wrong. Please try again.', 'data' => APP_DEBUG ? ['error' => $e->getMessage()] : (object) []]);
    } else {
        echo APP_DEBUG ? '<pre>' . htmlspecialchars((string) $e) . '</pre>' : 'Something went wrong. Please try again.';
    }
});

foreach (['db', 'security', 'helpers', 'auth', 'notifications', 'files', 'subscriptions', 'shops', 'pricing', 'documents', 'orders', 'qr', 'cv', 'reports'] as $m) {
    require PH_ROOT . '/core/' . $m . '.php';
}
