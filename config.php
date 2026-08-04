<?php
/**
 * Global configuration & bootstrap. Required once by index.php before
 * the router dispatches to a page — everything below is available to
 * every file under pages/, vendor/, account/, admin/ without them
 * needing to require anything themselves.
 */

if (session_status() === PHP_SESSION_NONE) {
    $settings = require __DIR__ . '/config/settings.php';
    session_name($settings['session']['name']);
    session_set_cookie_params($settings['session']['lifetime']);
    session_start();
    unset($settings);
}

error_reporting(E_ALL);
ini_set('display_errors', filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN) ? '1' : '0');

define('SITE_ROOT', __DIR__);

require_once SITE_ROOT . '/config/database.php';
require_once SITE_ROOT . '/includes/helpers.php';
require_once SITE_ROOT . '/includes/auth.php';
require_once SITE_ROOT . '/services/notifier.php';

// Plain query functions, one file per table (see database/schema/) —
// nothing here is a class, just functions grouped by the entity they
// query.
require_once SITE_ROOT . '/data/marketplace_types.php';
require_once SITE_ROOT . '/data/vendors.php';
require_once SITE_ROOT . '/data/categories.php';
require_once SITE_ROOT . '/data/vendor_category_requests.php';
require_once SITE_ROOT . '/data/artisan_profiles.php';
require_once SITE_ROOT . '/data/business_profiles.php';
require_once SITE_ROOT . '/data/products.php';
require_once SITE_ROOT . '/data/customers.php';
require_once SITE_ROOT . '/data/admin_users.php';
