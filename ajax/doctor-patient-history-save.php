<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$patientId = (int) ($_POST['patient_id'] ?? 0);
$title = mb_substr(clean($_POST['title'] ?? ''), 0, 180);
$description = mb_substr(clean($_POST['description'] ?? ''), 0, 5000);
$status = $_POST['status'] ?? 'ongoing';
$visitDate = $_POST['visit_date'] ?? '';

if ($title === '') {
    json_response(false, [], 'Please enter a title for this entry.');
}
if (!in_array($status, ['ongoing', 'improving', 'stable', 'recovered', 'critical'], true)) {
    $status = 'ongoing';
}
$visitDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitDate) ? $visitDate : null;

$stmt = mysqli_prepare(db(), 'SELECT 1 FROM appointments WHERE patient_id = ? AND doctor_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $patientId, $doctorId);
mysqli_stmt_execute($stmt);
$hasAppointment = (bool) mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if (!$hasAppointment) {
    json_response(false, [], 'You can only add history for a patient who has booked an appointment with you.');
}

$stmt = mysqli_prepare(db(), 'INSERT INTO patient_medical_history (doctor_id, patient_id, title, description, status, visit_date) VALUES (?, ?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'iissss', $doctorId, $patientId, $title, $description, $status, $visitDate);
mysqli_stmt_execute($stmt);
$historyId = mysqli_insert_id(db());
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'doctor', 'add_patient_history', "Added history entry #$historyId for patient #$patientId");
json_response(true, ['reload' => true], 'History entry added.');
