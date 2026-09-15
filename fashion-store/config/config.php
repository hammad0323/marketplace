<?php
/**
 * Global site configuration. Edit the DB_* constants for your hosting
 * environment (cPanel -> MySQL Databases) and everything else works.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'fashion_store');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Environment ----
define('APP_ENV', 'production'); // 'production' hides PHP errors from output
error_reporting(E_ALL);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');

// ---- Paths / URLs ----
define('ROOT_PATH', dirname(__DIR__));
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
// Normalise base URL to the fashion-store folder root regardless of current script depth.
$baseDir = $scriptDir;
foreach (['/admin', '/ajax', '/account'] as $sub) {
    if (substr($baseDir, -strlen($sub)) === $sub) {
        $baseDir = substr($baseDir, 0, -strlen($sub));
    }
}
define('BASE_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $baseDir);
define('UPLOAD_DIR', ROOT_PATH . '/uploads');
define('UPLOAD_URL', BASE_URL . '/uploads');

// ---- Session ----
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $scheme === 'https',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Misc ----
define('STORE_CURRENCY_SYMBOL', 'Rs.');
date_default_timezone_set('Asia/Karachi');
