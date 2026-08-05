<?php
require __DIR__ . '/../config/config.php';

$vendor = mp_require_vendor();
mp_verify_csrf();

$orderItemId = (int) ($_POST['order_item_id'] ?? 0);
$status = $_POST['status'] ?? '';

if (in_array($status, ['processing', 'shipped', 'delivered', 'cancelled'], true)) {
    if (mp_update_order_item_status($orderItemId, $status, $vendor['id'])) {
        mp_flash('success', 'Order item updated.');
    } else {
        mp_flash('error', 'Could not update that order item.');
    }
}

mp_redirect('orders.php');
