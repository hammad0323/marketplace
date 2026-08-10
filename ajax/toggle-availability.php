<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in() || current_user_role() !== 'provider') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$serviceId = (int) ($_POST['service_id'] ?? 0);
$date = clean_input($_POST['date'] ?? '');
if (!$serviceId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

$provider = db_select_one($conn, 'SELECT id FROM providers WHERE user_id = ?', [(int) current_user_id()]);
$service = $provider ? db_select_one($conn, 'SELECT id FROM services WHERE id = ? AND provider_id = ?', [$serviceId, (int) $provider['id']]) : null;
if (!$service) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
}

$existing = db_select_one($conn, 'SELECT * FROM service_availability WHERE service_id = ? AND date = ?', [$serviceId, $date]);
if ($existing && $existing['status'] === 'reserved') {
    echo json_encode(['ok' => false, 'error' => 'This date already has a confirmed booking.']);
    exit;
}

$newStatus = ($existing && $existing['status'] === 'blocked') ? 'available' : 'blocked';
if ($newStatus === 'available' && $existing) {
    db_execute($conn, 'DELETE FROM service_availability WHERE id = ?', [(int) $existing['id']]);
} else {
    mark_service_dates($conn, $serviceId, $date, $date, 'blocked');
}

echo json_encode(['ok' => true, 'status' => $newStatus]);
