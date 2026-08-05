<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
mp_verify_csrf();

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));

$product = mp_find_product($productId);
if ($product && $product['status'] === 'published') {
    mp_cart_add_item($customer['id'], $productId, $quantity);
    mp_flash('success', 'Added to cart.');
}

mp_redirect($_POST['redirect_to'] ?? (ROUTE_CART . 'view.php'));
