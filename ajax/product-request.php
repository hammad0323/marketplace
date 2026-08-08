<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('patient');

$db = db();
$patientId = current_profile_id();
$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));
$contactPhone = clean($_POST['contact_phone'] ?? '');
$notes = clean($_POST['notes'] ?? '');

$stmt = mysqli_prepare($db, "
    SELECT dp.*, d.user_id AS doctor_user_id, d.is_premium
    FROM doctor_products dp JOIN doctors d ON d.id = dp.doctor_id
    WHERE dp.id = ? AND dp.is_active = 1 LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$product = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$product || !$product['is_premium']) {
    json_response(false, [], 'This listing is not available.');
}
if ($product['type'] === 'product') {
    if ($product['stock'] !== null && $quantity > (int) $product['stock']) {
        json_response(false, [], 'Only ' . (int) $product['stock'] . ' left in stock.');
    }
} else {
    $quantity = 1;
}

$totalAmount = round((float) $product['price'] * $quantity, 2);
$orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

mysqli_begin_transaction($db);
try {
    $stmt = mysqli_prepare($db, 'INSERT INTO orders (patient_id, doctor_id, order_number, total_amount, contact_phone, notes, status) VALUES (?,?,?,?,?,?,\'pending\')');
    mysqli_stmt_bind_param($stmt, 'iisdss', $patientId, $product['doctor_id'], $orderNumber, $totalAmount, $contactPhone, $notes);
    mysqli_stmt_execute($stmt);
    $orderId = mysqli_insert_id($db);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db, 'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)');
    mysqli_stmt_bind_param($stmt, 'iiid', $orderId, $productId, $quantity, $product['price']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($product['type'] === 'product' && $product['stock'] !== null) {
        $stmt = mysqli_prepare($db, 'UPDATE doctor_products SET stock = stock - ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $quantity, $productId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($db);
} catch (Throwable $e) {
    mysqli_rollback($db);
    json_response(false, [], 'Could not submit your request. Please try again.');
}

$patientName = current_user()['full_name'];
notify_user(
    (int) $product['doctor_user_id'],
    'order',
    'New order request',
    $patientName . ' requested ' . $quantity . 'x ' . $product['name'] . ' (' . format_currency($totalAmount) . ').',
    '/doctor/products'
);

json_response(true, ['order_id' => $orderId], 'Your request has been sent to the doctor.');
