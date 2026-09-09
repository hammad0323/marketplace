<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$patientId = (int) ($_POST['patient_id'] ?? 0);
$newName = mb_substr(clean($_POST['name'] ?? ''), 0, 150);

if ($newName === '') {
    json_response(false, [], 'Please enter a name.');
}

$stmt = mysqli_prepare(db(), "SELECT p.user_id FROM patients p
    WHERE p.id = ? AND (
        EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.doctor_id = ?)
        OR EXISTS (SELECT 1 FROM chat_conversations c WHERE c.patient_id = p.id AND c.doctor_id = ?)
    ) LIMIT 1");
mysqli_stmt_bind_param($stmt, 'iii', $patientId, $doctorId, $doctorId);
mysqli_stmt_execute($stmt);
$patient = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$patient) {
    json_response(false, [], 'Patient not found.');
}

$stmt = mysqli_prepare(db(), 'UPDATE users SET full_name = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $newName, $patient['user_id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'doctor', 'rename_patient', "Renamed patient #$patientId to \"$newName\"");
json_response(true, ['name' => $newName], 'Patient renamed.');
