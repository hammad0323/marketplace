<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
if (!is_logged_in()) {
    json_response(false, [], 'Please log in.');
}

$current = (string) ($_POST['current_password'] ?? '');
$new = (string) ($_POST['new_password'] ?? '');
$confirm = (string) ($_POST['confirm_password'] ?? '');

if (strlen($new) < 8) {
    json_response(false, ['errors' => ['new_password' => 'New password must be at least 8 characters.']], 'Please fix the errors below.');
}
if ($new !== $confirm) {
    json_response(false, ['errors' => ['confirm_password' => 'Passwords do not match.']], 'Please fix the errors below.');
}

$stmt = mysqli_prepare(db(), 'SELECT password_hash FROM users WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$row = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$row || !password_verify($current, $row['password_hash'])) {
    json_response(false, ['errors' => ['current_password' => 'Current password is incorrect.']], 'Please fix the errors below.');
}

$hash = password_hash($new, PASSWORD_DEFAULT);
$stmt = mysqli_prepare(db(), 'UPDATE users SET password_hash = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $hash, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], current_role(), 'change_password', 'Password changed');
json_response(true, [], 'Password updated successfully.');
