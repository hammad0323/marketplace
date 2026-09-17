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
$newStatus = wh_input_post('status');
$validStatuses = ['pending', 'confirmed', 'cancelled', 'completed', 'hold'];

if (!in_array($newStatus, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
    exit;
}

$result = wh_set_booking_status($bookingId, $newStatus, $businessId, $_SESSION['admin_id']);
if (!$result['ok']) {
    $msg = $result['error'] === 'conflict'
        ? 'Cannot confirm — this hall/date/time slot is already booked by another confirmed booking.'
        : 'Could not update booking status.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

echo json_encode(['success' => true, 'status' => $newStatus]);
