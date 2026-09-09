<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$historyId = (int) ($_POST['id'] ?? 0);

$stmt = mysqli_prepare(db(), 'DELETE FROM patient_medical_history WHERE id = ? AND doctor_id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $historyId, $doctorId);
mysqli_stmt_execute($stmt);
$deleted = mysqli_stmt_affected_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if (!$deleted) {
    json_response(false, [], 'Entry not found.');
}

log_activity($_SESSION['user_id'], 'doctor', 'delete_patient_history', "Deleted history entry #$historyId");
json_response(true, [], 'Entry deleted.');
