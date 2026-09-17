<?php
require __DIR__ . '/../config.php';
header('Content-Type: application/json');
wh_require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
wh_csrf_verify();

$businessId = wh_current_business_id();
$bookingId = (int) wh_input_post('booking_id');
$amount = wh_decimal(wh_input_post('amount', 0));
$date = wh_input_post('payment_date') ?: date('Y-m-d');
$method = wh_input_post('payment_method', 'cash');
$notes = wh_input_post('notes');

$booking = wh_get_booking($bookingId, $businessId);
if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Booking not found.']);
    exit;
}
if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid payment amount.']);
    exit;
}
$validMethods = ['cash', 'bank_transfer', 'jazzcash', 'easypaisa', 'card', 'other'];
if (!in_array($method, $validMethods, true)) {
    $method = 'other';
}

wh_add_payment($businessId, $bookingId, $amount, $date, $method, $notes, $_SESSION['admin_id']);
$updated = wh_get_booking($bookingId, $businessId);

echo json_encode([
    'success' => true,
    'paid_amount' => $updated['paid_amount'],
    'balance' => $updated['balance'],
    'payment_status' => $updated['payment_status'],
]);
