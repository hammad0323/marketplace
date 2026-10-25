<?php
/**
 * Loaded at the top of every entry point (index.php, admin pages, ajax and api endpoints).
 * Loads configuration, starts the secure session and pulls in every shared module.
 */

if (!defined('UC_ROOT')) {
    define('UC_ROOT', dirname(__DIR__));
}

if (!is_file(UC_ROOT . '/config/config.php')) {
    http_response_code(503);
    exit('<div style="font-family:sans-serif;max-width:600px;margin:80px auto;line-height:1.6">'
        . '<h2>Urban Collections is not configured yet</h2>'
        . '<p>Copy <code>config/config.sample.php</code> to <code>config/config.php</code>, fill in your database '
        . 'details and import <code>database/urban_collections.sql</code>. See <code>docs/INSTALL.md</code>.</p></div>');
}
require_once UC_ROOT . '/config/config.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Karachi');

error_reporting(E_ALL);
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', UC_ROOT . '/logs/php-error.log');
} else {
    ini_set('display_errors', '1');
}

// Base path of the site ("" when installed at the domain root, "/store" in a sub-folder)
define('BASE_PATH', rtrim((string) parse_url(SITE_URL, PHP_URL_PATH), '/'));

require_once UC_ROOT . '/includes/db.php';
require_once UC_ROOT . '/includes/security.php';
require_once UC_ROOT . '/includes/helpers.php';
require_once UC_ROOT . '/includes/settings.php';
require_once UC_ROOT . '/includes/auth.php';
require_once UC_ROOT . '/includes/uploads.php';
require_once UC_ROOT . '/includes/catalog.php';
require_once UC_ROOT . '/includes/cart.php';
require_once UC_ROOT . '/includes/shipping.php';
require_once UC_ROOT . '/includes/coupons.php';
require_once UC_ROOT . '/includes/orders.php';
require_once UC_ROOT . '/includes/payments.php';
require_once UC_ROOT . '/includes/mailer.php';
require_once UC_ROOT . '/includes/seo.php';
require_once UC_ROOT . '/includes/sections.php';
require_once UC_ROOT . '/includes/content.php';
require_once UC_ROOT . '/includes/studio.php';
require_once UC_ROOT . '/includes/alterations.php';

uc_start_session();
uc_send_security_headers();
