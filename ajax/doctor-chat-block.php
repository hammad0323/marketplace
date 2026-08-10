<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$db = db();
$doctorId = current_profile_id();
$patientId = (int) ($_POST['patient_id'] ?? 0);
$blocked = !empty($_POST['blocked']) ? 1 : 0;

$stmt = mysqli_prepare($db, 'UPDATE chat_conversations SET is_blocked = ? WHERE patient_id = ? AND doctor_id = ?');
mysqli_stmt_bind_param($stmt, 'iii', $blocked, $patientId, $doctorId);
mysqli_stmt_execute($stmt);
$updated = mysqli_stmt_affected_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if (!$updated) {
    json_response(false, [], 'Conversation not found.');
}

log_activity((int) $_SESSION['user_id'], 'doctor', $blocked ? 'block_patient_chat' : 'unblock_patient_chat', "Patient #$patientId");
json_response(true, ['is_blocked' => (bool) $blocked], $blocked ? 'Patient blocked from messaging you.' : 'Patient unblocked.');
