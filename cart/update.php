<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
mp_verify_csrf();

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = (int) ($_POST['quantity'] ?? 1);

mp_cart_update_quantity($customer['id'], $productId, $quantity);

mp_redirect('view.php');
