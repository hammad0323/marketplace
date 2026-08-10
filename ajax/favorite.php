<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'login_required']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}
$token = $_POST['csrf_token'] ?? '';
if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$type = clean_input($_POST['type'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
if (!in_array($type, ['service', 'provider', 'city', 'trip'], true) || !$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_target']);
    exit;
}

$userId = (int) current_user_id();
$existing = db_select_one($conn, 'SELECT id FROM favorites WHERE user_id = ? AND favoritable_type = ? AND favoritable_id = ?', [$userId, $type, $id]);

if ($existing) {
    db_execute($conn, 'DELETE FROM favorites WHERE id = ?', [(int) $existing['id']]);
    echo json_encode(['ok' => true, 'favorited' => false]);
} else {
    db_execute($conn, 'INSERT INTO favorites (user_id, favoritable_type, favoritable_id) VALUES (?, ?, ?)', [$userId, $type, $id]);
    echo json_encode(['ok' => true, 'favorited' => true]);
}
