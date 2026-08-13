<?php
/**
 * Application bootstrap. Every entry-point page requires this file first.
 */
define('APP_LOADED', true);

define('APP_DEBUG', (getenv('APP_DEBUG') === '1'));
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

// Fallback only — the real, editable site name is the "site_name" row in
// the settings table (Admin -> Settings -> General). This constant is what
// shows up before that table can be reached: the admin login page before
// config finishes loading, and as get_setting()'s default if the row is
// ever missing.
define('APP_NAME', 'Toursity');
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// ---------------------------------------------------------------------
// BASE_PATH — the URL prefix this app is deployed under, e.g. "/beta"
// for https://www.example.com/beta, or "" for a domain root deploy.
// Every asset link, redirect, internal href, and AJAX call in the app
// goes through this one constant (via ASSETS_URL/UPLOAD_URL/APP_URL
// below, and the url()/redirect() helpers in includes/functions.php),
// so moving the site between a subfolder and the domain root normally
// needs NO code changes — it's auto-detected from where the app's
// files sit relative to the web server's document root.
//
// If your host reports DOCUMENT_ROOT incorrectly (some do, especially
// with symlinked docroots) and pages/CSS/JS still 404 after uploading,
// override it here manually instead — uncomment and edit the line
// below with your real subfolder (no trailing slash, e.g. '/beta'):
// define('BASE_PATH', '/beta');
if (!defined('BASE_PATH')) {
    $__docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $__appRoot = rtrim(str_replace('\\', '/', ROOT_PATH), '/');
    $__basePath = '';
    if ($__docRoot !== '' && strpos($__appRoot, $__docRoot) === 0) {
        $__basePath = substr($__appRoot, strlen($__docRoot));
    }
    define('BASE_PATH', $__basePath);
    unset($__docRoot, $__appRoot, $__basePath);
}

define('UPLOAD_URL', BASE_PATH . '/uploads');
define('ASSETS_URL', BASE_PATH . '/assets');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
// ORIGIN has no path — use it with $_SERVER['REQUEST_URI'] (which already
// includes BASE_PATH, since that's the real path the browser requested)
// rather than APP_URL, or the base path ends up duplicated.
define('ORIGIN', $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
define('APP_URL', ORIGIN . BASE_PATH);

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
