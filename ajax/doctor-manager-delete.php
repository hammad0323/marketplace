<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$managerId = (int) ($_POST['manager_id'] ?? 0);

$stmt = mysqli_prepare(db(), 'SELECT user_id FROM doctor_managers WHERE id = ? AND doctor_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $managerId, $doctorId);
mysqli_stmt_execute($stmt);
$manager = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$manager) {
    json_response(false, [], 'Staff account not found.');
}

// Deleting the user cascades to doctor_managers (fk_manager_user ON DELETE CASCADE) — removing the login removes their queue access entirely.
$stmt = mysqli_prepare(db(), 'DELETE FROM users WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $manager['user_id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'doctor', 'remove_manager', "Removed staff login #$managerId");
json_response(true, [], 'Staff login removed.');
