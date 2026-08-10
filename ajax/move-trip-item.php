<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false]);
    exit;
}

$tripId = (int) ($_POST['trip_id'] ?? 0);
$dayId = (int) ($_POST['day_id'] ?? 0);
$itemIds = array_map('intval', (array) ($_POST['item_ids'] ?? []));

$trip = db_select_one($conn, 'SELECT id FROM trips WHERE id = ? AND user_id = ?', [$tripId, (int) current_user_id()]);
$day = $trip ? db_select_one($conn, 'SELECT id FROM trip_days WHERE id = ? AND trip_id = ?', [$dayId, $tripId]) : null;
if (!$day) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

foreach ($itemIds as $index => $itemId) {
    db_execute(
        $conn,
        'UPDATE trip_items ti JOIN trip_days td ON td.id = ti.trip_day_id SET ti.trip_day_id = ?, ti.sort_order = ? WHERE ti.id = ? AND td.trip_id = ?',
        [$dayId, $index, $itemId, $tripId]
    );
}

recalculate_trip_budget($conn, $tripId);
echo json_encode(['ok' => true]);
