<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in() || current_user_role() !== 'customer') {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$userId = (int) current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $trips = db_select($conn, 'SELECT id, trip_name FROM trips WHERE user_id = ? AND status != "cancelled" ORDER BY created_at DESC LIMIT 10', [$userId]);
    echo json_encode(['ok' => true, 'trips' => $trips]);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false]);
    exit;
}

$serviceId = (int) ($_POST['service_id'] ?? 0);
$tripId = (int) ($_POST['trip_id'] ?? 0);
$service = db_select_one($conn, 'SELECT id, title FROM services WHERE id = ? AND status = "approved"', [$serviceId]);
$trip = db_select_one($conn, 'SELECT id FROM trips WHERE id = ? AND user_id = ?', [$tripId, $userId]);
if (!$service || !$trip) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$day = db_select_one($conn, 'SELECT id FROM trip_days WHERE trip_id = ? ORDER BY day_number LIMIT 1', [$tripId]);
if (!$day) {
    $dayId = db_insert_get_id($conn, 'INSERT INTO trip_days (trip_id, day_number) VALUES (?, 1)', [$tripId]);
} else {
    $dayId = (int) $day['id'];
}

$maxOrder = (int) (db_select_one($conn, 'SELECT COALESCE(MAX(sort_order),-1) AS m FROM trip_items WHERE trip_day_id = ?', [$dayId])['m']);
db_execute($conn, 'INSERT INTO trip_items (trip_day_id, service_id, sort_order) VALUES (?, ?, ?)', [$dayId, $serviceId, $maxOrder + 1]);
db_execute($conn, 'INSERT IGNORE INTO trip_services (trip_id, service_id) VALUES (?, ?)', [$tripId, $serviceId]);
recalculate_trip_budget($conn, $tripId);

echo json_encode(['ok' => true, 'message' => $service['title'] . ' added to your trip.']);
