<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}
$userId = (int) current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        echo json_encode(['ok' => false]);
        exit;
    }
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        db_execute($conn, 'UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
    } elseif (($_POST['action'] ?? '') === 'mark_read' && !empty($_POST['id'])) {
        db_execute($conn, 'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [(int) $_POST['id'], $userId]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

$notifications = db_select($conn, 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15', [$userId]);
$unread = db_count($conn, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
foreach ($notifications as &$n) {
    $n['time_ago'] = time_ago($n['created_at']);
    if (!empty($n['link'])) {
        $n['link'] = url($n['link']);
    }
}
echo json_encode(['ok' => true, 'unread' => $unread, 'items' => $notifications]);
