<?php
/**
 * Application bootstrap. Every entry-point page requires this file first.
 */
define('APP_LOADED', true);

define('APP_DEBUG', (getenv('APP_DEBUG') === '1'));
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

define('APP_NAME', 'Wanderly');
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('UPLOAD_URL', '/uploads');
define('ASSETS_URL', '/assets');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
define('APP_URL', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

define('SESSION_LIFETIME', 60 * 60 * 24 * 7);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// --- secure session bootstrap -------------------------------------------
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if ($scheme === 'https') {
    ini_set('session.cookie_secure', '1');
}
session_name('wanderly_session');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/booking-functions.php';
require_once ROOT_PATH . '/includes/mailer.php';
require_once ROOT_PATH . '/includes/messaging-functions.php';
require_once ROOT_PATH . '/includes/review-functions.php';
require_once ROOT_PATH . '/includes/membership-functions.php';
require_once ROOT_PATH . '/includes/trip-functions.php';
