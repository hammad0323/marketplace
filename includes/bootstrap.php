<?php
/**
 * Single entry bootstrap - every public page, API endpoint and admin page
 * includes this file first.
 */
define('FS_BOOT', true);
define('FS_ROOT', dirname(__DIR__));

require FS_ROOT . '/includes/config.php';
require FS_ROOT . '/includes/database.php';
require FS_ROOT . '/includes/functions.php';
require FS_ROOT . '/includes/security.php';
require FS_ROOT . '/includes/upload.php';
require FS_ROOT . '/includes/auth.php';
require FS_ROOT . '/includes/seo.php';
require FS_ROOT . '/includes/catalog.php';
require FS_ROOT . '/includes/search.php';
require FS_ROOT . '/includes/inventory.php';
require FS_ROOT . '/includes/cart.php';
require FS_ROOT . '/includes/orders.php';
require FS_ROOT . '/includes/notify.php';

if (PHP_SAPI !== 'cli') {
    fs_session_start();
    fs_security_headers();
}
