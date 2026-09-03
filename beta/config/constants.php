<?php
/**
 * ============================================================
 *  CENTRAL CONFIGURATION — change BASE_URL here and nothing
 *  else in the project needs to change. Every URL, asset link
 *  and redirect in the app is built from this one constant.
 * ============================================================
 */
define('BASE_URL', 'https://www.beglet.com/beta/');

// Local/dev override: if running on localhost, auto-detect the base
// path so the app works out of the box before you edit BASE_URL above.
if (isset($_SERVER['HTTP_HOST']) && (
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
        strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
    )) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Strip a trailing /admin, /shop, /employee, /customer, /actions segment
    // so BASE_URL always points at the beta/ root regardless of which
    // page triggered the bootstrap.
    $scriptDir = preg_replace('#/(admin|shop|employee|customer|actions)(/.*)?$#', '', $scriptDir);
    $scriptDir = rtrim($scriptDir, '/') . '/';
    define('BASE_URL_RUNTIME', $scheme . '://' . $_SERVER['HTTP_HOST'] . $scriptDir);
} else {
    define('BASE_URL_RUNTIME', BASE_URL);
}

define('ROLE_ADMIN', 'admin');
define('ROLE_SHOP_OWNER', 'shop_owner');
define('ROLE_SHOP_STAFF', 'shop_staff');
define('ROLE_CUSTOMER', 'customer');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);

define('DEFAULT_CATEGORY_IMAGE', 'assets/images/default-category.png');
define('DEFAULT_PRODUCT_IMAGE', 'assets/images/default-product.png');
define('DEFAULT_SHOP_LOGO', 'assets/images/default-shop-logo.png');
define('DEFAULT_SHOP_COVER', 'assets/images/default-shop-cover.png');
define('DEFAULT_AVATAR', 'assets/images/default-avatar.png');

define('ALL_STAFF_PERMISSIONS', [
    'view_dashboard'      => 'View Dashboard',
    'manage_products'     => 'Manage Products',
    'add_product'         => 'Add Product',
    'edit_product'        => 'Edit Product',
    'delete_product'      => 'Delete Product',
    'manage_inventory'    => 'Manage Inventory',
    'view_orders'         => 'View Orders',
    'manage_orders'       => 'Manage Orders',
    'confirm_orders'      => 'Confirm Orders',
    'view_customers'      => 'View Customers',
    'manage_reviews'      => 'Manage Reviews',
    'manage_shop_profile' => 'Manage Shop Profile',
    'manage_shop_design'  => 'Manage Shop Design',
    'manage_shop_sections'=> 'Manage Shop Sections',
    'view_revenue'        => 'View Revenue',
    'view_commission'     => 'View Commission',
    'manage_payments'     => 'Manage Payments',
    'view_reports'        => 'View Reports',
    'manage_seo'          => 'Manage SEO',
]);
