<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$userId = (int) ($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

$statusMap = ['suspend' => 'suspended', 'activate' => 'active', 'ban' => 'banned'];
if (!isset($statusMap[$action])) {
    json_response(false, [], 'Invalid action.');
}

$stmt = mysqli_prepare($db, 'SELECT id, role FROM users WHERE id = ? AND role = \'patient\' LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$targetUser = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$targetUser) {
    json_response(false, [], 'Patient not found.');
}

$newStatus = $statusMap[$action];
$stmt = mysqli_prepare($db, 'UPDATE users SET status = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $newStatus, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'admin', 'user_' . $action, "User #$userId -> $newStatus");
json_response(true, [], 'Account updated.');
