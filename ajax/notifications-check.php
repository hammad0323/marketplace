<?php
require __DIR__ . '/../config/config.php';

if (!is_logged_in()) {
    json_response(false, [], 'Please log in.');
}

$userId = (int) $_SESSION['user_id'];

$stmt = mysqli_prepare(db(), 'SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$unreadCount = (int) mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(db(), 'SELECT id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 6');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$notifications = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

foreach ($notifications as &$n) {
    $n['title'] = e($n['title']);
    $n['message'] = e($n['message']);
    $n['link'] = e($n['link'] ?: '#');
}
unset($n);

json_response(true, ['unread_count' => $unreadCount, 'notifications' => $notifications]);
