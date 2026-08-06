<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

$product = mp_find_product((int) ($_POST['id'] ?? 0));
if ($product) {
    $newStatus = $product['status'] === 'published' ? 'draft' : 'published';
    mp_set_product_status($product['id'], $newStatus);
    mp_log_activity('admin', $admin['id'], 'product.status_changed', 'product', $product['id'], "{$product['title']} -> {$newStatus}");
    mp_flash('success', 'Product status updated.');
}

mp_redirect('products.php');
