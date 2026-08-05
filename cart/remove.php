<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
mp_verify_csrf();

$productId = (int) ($_POST['product_id'] ?? 0);
mp_cart_remove_item($customer['id'], $productId);
mp_flash('success', 'Removed from cart.');

mp_redirect('view.php');
