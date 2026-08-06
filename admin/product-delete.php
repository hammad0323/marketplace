<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

$product = mp_find_product((int) ($_POST['id'] ?? 0));
if ($product) {
    mp_delete_product($product['id']);
    mp_log_activity('admin', $admin['id'], 'product.deleted', 'product', $product['id'], $product['title']);
    mp_flash('success', $product['title'] . ' deleted.');
}

mp_redirect('products.php');
