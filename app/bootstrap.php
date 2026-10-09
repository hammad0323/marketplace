<?php
/**
 * Application bootstrap — loaded by every entry point
 * (index.php, admin/*.php, tools/cron.php).
 */

if (defined('BEGLET_BOOTED')) {
    return;
}
define('BEGLET_BOOTED', true);
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// ---- Configuration: prefer a file above the web root -----------------
$configCandidates = [
    dirname(ROOT_PATH) . '/beglet-config.php',
    APP_PATH . '/config.php',
];
$configLoaded = false;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        require $candidate;
        $configLoaded = true;
        break;
    }
}
if (!$configLoaded) {
    http_response_code(503);
    exit('Beglet is not configured yet. Copy app/config.sample.php to app/config.php (or ../beglet-config.php) and fill in your database details. See docs/DEPLOYMENT.md.');
}

// ---- Error handling ------------------------------------------------------
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');

mb_internal_encoding('UTF-8');

// ---- Core includes -------------------------------------------------------
require APP_PATH . '/includes/db.php';
require APP_PATH . '/includes/helpers.php';
require APP_PATH . '/includes/settings.php';
require APP_PATH . '/includes/security.php';
require APP_PATH . '/includes/auth.php';
require APP_PATH . '/includes/uploads.php';
require APP_PATH . '/includes/catalog.php';
require APP_PATH . '/includes/cart.php';
require APP_PATH . '/includes/wishlist.php';
require APP_PATH . '/includes/shipping.php';
require APP_PATH . '/includes/coupons.php';
require APP_PATH . '/includes/orders.php';
require APP_PATH . '/includes/payments.php';
require APP_PATH . '/includes/mailer.php';
require APP_PATH . '/includes/seo.php';
require APP_PATH . '/includes/homepage.php';

date_default_timezone_set(setting('timezone', 'Asia/Karachi') ?: 'UTC');
db_sync_timezone();

if (PHP_SAPI !== 'cli') {
    start_secure_session();
    send_security_headers();
}
