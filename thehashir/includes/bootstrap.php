<?php
/**
 * Loads configuration and helpers. Included once by index.php.
 */

require __DIR__ . '/../config/config.php';

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}

date_default_timezone_set(DEFAULT_TIMEZONE);
mb_internal_encoding('UTF-8');

// Secure session cookie (contact form token, back-office logins).
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params(array('lifetime' => 0, 'path' => BASE_PATH . '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
} else {
    session_set_cookie_params(0, BASE_PATH . '/; samesite=Lax', '', $secure, true);
}
session_name('thsid');
session_start();

// Basic security headers
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
if (BETA_MODE) {
    // Keep the beta copy out of search results
    header('X-Robots-Tag: noindex, nofollow', true);
}

require __DIR__ . '/functions.php';
require __DIR__ . '/storage.php';
require __DIR__ . '/db.php';
require __DIR__ . '/data.php';
require __DIR__ . '/partials.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/geo.php';
require __DIR__ . '/blocks.php';

// Settings saved in the Super Admin override the defaults from config.php
$SOCIAL_LINKS = array_filter((array)setting('social', $SOCIAL_LINKS), 'strlen');
$TICKER_ITEMS = (array)setting('ticker', $TICKER_ITEMS);
$HOME_SLIDES = (array)setting('slides', $HOME_SLIDES);
