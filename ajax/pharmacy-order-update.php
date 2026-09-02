<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('pharmacy');

$db = db();
$pharmacyId = current_profile_id();
$orderId = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowedTransitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['completed'],
];

$stmt = mysqli_prepare($db, "
    SELECT o.*, u.id AS patient_user_id,
        (SELECT GROUP_CONCAT(dp.name SEPARATOR ', ') FROM order_items oi JOIN doctor_products dp ON dp.id = oi.product_id WHERE oi.order_id = o.id) AS items_label
    FROM orders o JOIN patients p ON p.id = o.patient_id JOIN users u ON u.id = p.user_id
    WHERE o.id = ? AND o.pharmacy_id = ? AND o.seller_type = 'pharmacy' LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'ii', $orderId, $pharmacyId);
mysqli_stmt_execute($stmt);
$order = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$order) {
    json_response(false, [], 'Order not found.');
}
if (!in_array($status, $allowedTransitions[$order['status']] ?? [], true)) {
    json_response(false, [], 'That status change is not allowed from the current status.');
}

$stmt = mysqli_prepare($db, 'UPDATE orders SET status = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $status, $orderId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$statusLabels = ['confirmed' => 'confirmed', 'completed' => 'marked completed', 'cancelled' => 'cancelled'];
$pharmacyName = current_user()['full_name'];
notify_user(
    (int) $order['patient_user_id'],
    'order',
    'Your order was ' . $statusLabels[$status],
    $pharmacyName . ' has ' . $statusLabels[$status] . ' your order for ' . $order['items_label'] . '.',
    '/patient/orders'
);

json_response(true, ['status' => $status], 'Order updated.');
