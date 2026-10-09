<?php
/**
 * Loaded by every entry point (storefront router, admin pages, AJAX, callbacks).
 */
define('EBAYA', true);
define('ROOT_PATH', dirname(__DIR__));
define('INC_PATH', __DIR__);

if (!is_file(ROOT_PATH . '/config/config.php')) {
    http_response_code(500);
    exit('Ebaya is not configured yet. Copy config/config.sample.php to config/config.php and enter your database details (see docs/INSTALL.md).');
}
require ROOT_PATH . '/config/config.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC');

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/storage/logs/php-error.log');

define('BASE_PATH', rtrim((string)parse_url(SITE_URL, PHP_URL_PATH), '/'));

require INC_PATH . '/db.php';
require INC_PATH . '/helpers.php';
require INC_PATH . '/validation.php';
require INC_PATH . '/crypto.php';
require INC_PATH . '/settings.php';
require INC_PATH . '/auth.php';
require INC_PATH . '/upload.php';
require INC_PATH . '/catalog.php';
require INC_PATH . '/cart.php';
require INC_PATH . '/shipping.php';
require INC_PATH . '/checkout.php';
require INC_PATH . '/orders.php';
require INC_PATH . '/mailer.php';
require INC_PATH . '/payments.php';
require INC_PATH . '/seo.php';
require INC_PATH . '/homepage.php';

session_bootstrap();
