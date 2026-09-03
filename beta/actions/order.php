<?php
require __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!current_customer()) { echo json_encode(['success' => false, 'message' => 'Login required.']); exit; }
$customer = current_customer();
$do = $_POST['do'] ?? '';

if ($do === 'cancel_shop_order') {
    $shopOrderId = (int)($_POST['shop_order_id'] ?? 0);
    $shopOrder = db_fetch_one("SELECT so.* FROM shop_orders so JOIN orders o ON o.id=so.order_id
        WHERE so.id=? AND o.customer_id=?", 'ii', [$shopOrderId, $customer['id']]);
    if (!$shopOrder) { echo json_encode(['success' => false, 'message' => 'Order not found.']); exit; }
    if (!in_array($shopOrder['status'], ['placed', 'confirmed'], true)) {
        echo json_encode(['success' => false, 'message' => 'This order can no longer be cancelled.']); exit;
    }
    db_exec("UPDATE shop_orders SET status='cancelled' WHERE id=?", 'i', [$shopOrderId]);
    db_insert("INSERT INTO order_status_history (shop_order_id, status, note) VALUES (?, 'cancelled', 'Cancelled by customer')", 'i', [$shopOrderId]);
    echo json_encode(['success' => true, 'message' => 'Order cancelled.']);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
