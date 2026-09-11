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
$newPatientName = clean($_POST['new_patient_name'] ?? '');
$newPatientContact = clean($_POST['new_patient_contact'] ?? '');
$date = $_POST['date'] ?? '';
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$type = ($_POST['consultation_type'] ?? '') === 'physical' ? 'physical' : 'online';
$reason = mb_substr(clean($_POST['reason'] ?? ''), 0, 500);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $startTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $endTime)) {
    json_response(false, [], 'Please pick a valid date and time slot.');
}
if (strlen($startTime) === 5) { $startTime .= ':00'; }
if (strlen($endTime) === 5) { $endTime .= ':00'; }
if (strtotime($date . ' ' . $startTime) < time()) {
    json_response(false, [], 'That time slot is in the past. Please pick another.');
}
if ($patientId <= 0 && $newPatientContact === '') {
    json_response(false, ['errors' => ['patient' => 'Search for an existing patient or enter a name and contact to add a new one.']], 'Please fix the errors below.');
}

$stmt = mysqli_prepare($db, 'SELECT consultation_fee_online, consultation_fee_physical, free_consultation FROM doctors WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'SELECT id FROM doctor_blocked_dates WHERE doctor_id = ? AND blocked_date = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'You marked this date as unavailable.');
}
mysqli_stmt_close($stmt);

$dayOfWeek = (int) date('w', strtotime($date));
$stmt = mysqli_prepare($db, "SELECT id FROM doctor_availability WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1
    AND (consultation_type = ? OR consultation_type = 'both') AND start_time <= ? AND end_time >= ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'iisss', $doctorId, $dayOfWeek, $type, $startTime, $endTime);
mysqli_stmt_execute($stmt);
if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'That slot is outside your available hours for this day.');
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND start_time = ? AND status IN ('pending','approved') LIMIT 1");
mysqli_stmt_bind_param($stmt, 'iss', $doctorId, $date, $startTime);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'That slot is already booked. Please pick another.');
}
mysqli_stmt_close($stmt);

if ($patientId > 0) {
    $stmt = mysqli_prepare($db, 'SELECT id FROM patients WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $patientId);
    mysqli_stmt_execute($stmt);
    if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
        mysqli_stmt_close($stmt);
        json_response(false, [], 'Patient not found.');
    }
    mysqli_stmt_close($stmt);
} else {
    [$ok, $message, $patientId] = find_or_create_patient_record($newPatientName, $newPatientContact);
    if (!$ok) {
        json_response(false, ['errors' => ['patient' => $message]], 'Please fix the errors below.');
    }
}

$fee = $doctor['free_consultation'] ? 0.00 : (float) ($type === 'physical' ? $doctor['consultation_fee_physical'] : $doctor['consultation_fee_online']);

$stmt = mysqli_prepare($db, "INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, consultation_type, status, fee, reason)
    VALUES (?, ?, ?, ?, ?, ?, 'approved', ?, ?)");
mysqli_stmt_bind_param($stmt, 'iissssds', $patientId, $doctorId, $date, $startTime, $endTime, $type, $fee, $reason);
mysqli_stmt_execute($stmt);
$appointmentId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'SELECT user_id FROM patients WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $patientId);
mysqli_stmt_execute($stmt);
$patientUserId = (int) mysqli_stmt_get_result($stmt)->fetch_assoc()['user_id'];
mysqli_stmt_close($stmt);

notify_user($patientUserId, 'appointment', 'Appointment scheduled', current_user()['full_name'] . ' scheduled a ' . $type . ' appointment for you on ' . format_date($date) . ' at ' . format_time12($startTime) . '.', '/patient/appointments');
log_activity($_SESSION['user_id'], 'doctor', 'doctor_book_appointment', "Doctor booked appointment #$appointmentId for patient #$patientId");

json_response(true, ['appointment_id' => $appointmentId], 'Appointment scheduled.');
