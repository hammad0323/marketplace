<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$managerId = (int) ($_POST['manager_id'] ?? 0);

$stmt = mysqli_prepare(db(), 'SELECT u.id, u.full_name, u.email FROM doctor_managers m JOIN users u ON u.id = m.user_id WHERE m.id = ? AND m.doctor_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $managerId, $doctorId);
mysqli_stmt_execute($stmt);
$manager = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$manager) {
    json_response(false, [], 'Staff account not found.');
}

$password = bin2hex(random_bytes(6));
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = mysqli_prepare(db(), 'UPDATE users SET password_hash = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $hash, $manager['id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

send_email($manager['email'], $manager['full_name'], 'Your password was reset — ' . get_setting('site_name', SITE_NAME),
    email_template('Password Reset', '<p>Your password was reset. New password: <strong>' . e($password) . '</strong></p>', 'Log In', APP_URL . '/login'));

log_activity($_SESSION['user_id'], 'doctor', 'reset_manager_password', "Reset password for staff #$managerId");
json_response(true, [], 'New password emailed to ' . $manager['email'] . '.');
