<?php
/**
 * App bootstrap: session + shared includes. Required once by
 * public/index.php before the router matches a module page.
 */

$config = require __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', $config['app_debug'] ? '1' : '0');

session_name($config['session']['name']);
session_set_cookie_params($config['session']['lifetime']);
session_start();

require __DIR__ . '/database.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/notifier.php';

require __DIR__ . '/queries/marketplace-types.php';
require __DIR__ . '/queries/vendors.php';
require __DIR__ . '/queries/categories.php';
require __DIR__ . '/queries/vendor-category-requests.php';
require __DIR__ . '/queries/artisan-profiles.php';
require __DIR__ . '/queries/business-profiles.php';
require __DIR__ . '/queries/products.php';
require __DIR__ . '/queries/customers.php';
require __DIR__ . '/queries/admin-users.php';
