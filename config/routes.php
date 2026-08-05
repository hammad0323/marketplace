<?php
/**
 * Centralized module base paths. This project doesn't use a front
 * controller / router — every page is still a real, directly reachable
 * .php file — but every internal link is built from these constants
 * instead of a hardcoded string, so moving or renaming a module folder
 * later is a one-line change here instead of a find-and-replace across
 * every page.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

define('ROUTE_HOME', '/');
define('ROUTE_STORE', '/store/');
define('ROUTE_ARTISAN', '/artisan/');
define('ROUTE_BUSINESS', '/business/');
define('ROUTE_OFFICIAL_STORE', '/official-store/');
define('ROUTE_CATEGORIES', '/categories/');
define('ROUTE_PRODUCTS', '/products/');
define('ROUTE_CART', '/cart/');
define('ROUTE_CHECKOUT', '/checkout/');
define('ROUTE_ORDERS', '/orders/');
define('ROUTE_VENDOR', '/vendor/');
define('ROUTE_CUSTOMER', '/customer/');
define('ROUTE_ADMIN', '/admin/');
define('ROUTE_ASSETS', '/assets/');
