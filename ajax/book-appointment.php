<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();

if (!is_logged_in()) {
    json_response(false, ['auth_required' => true], 'Please log in to book an appointment.');
}
if (current_role() !== 'patient') {
    json_response(false, [], 'Only patient accounts can book appointments.');
}

$db = db();
$doctorId = (int) ($_POST['doctor_id'] ?? 0);
$date = $_POST['date'] ?? '';
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$type = ($_POST['consultation_type'] ?? '') === 'physical' ? 'physical' : 'online';
$reason = mb_substr(clean($_POST['reason'] ?? ''), 0, 500);
$patientId = current_profile_id();

if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime) || !$patientId) {
    json_response(false, [], 'Invalid booking details. Please try again.');
}
if (strtotime($date . ' ' . $startTime) < time()) {
    json_response(false, [], 'That time slot is in the past. Please pick another.');
}

$stmt = mysqli_prepare($db, "SELECT d.id, d.consultation_fee_online, d.consultation_fee_physical, d.free_consultation, u.id AS user_id, u.full_name
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.id = ? AND d.verification_status = 'verified' AND u.status = 'active' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if (!$doctor) {
    json_response(false, [], 'This doctor is not available for booking.');
}

$stmt = mysqli_prepare($db, 'SELECT id FROM doctor_blocked_dates WHERE doctor_id = ? AND blocked_date = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'The doctor is unavailable on this date.');
}
mysqli_stmt_close($stmt);

$dayOfWeek = (int) date('w', strtotime($date));
$stmt = mysqli_prepare($db, "SELECT id FROM doctor_availability WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1
    AND (consultation_type = ? OR consultation_type = 'both') AND start_time <= ? AND end_time >= ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'iisss', $doctorId, $dayOfWeek, $type, $startTime, $endTime);
mysqli_stmt_execute($stmt);
if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'That slot is outside the doctor\'s available hours.');
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND start_time = ? AND status IN ('pending','approved') LIMIT 1");
mysqli_stmt_bind_param($stmt, 'iss', $doctorId, $date, $startTime);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'That slot was just booked by someone else. Please pick another.');
}
mysqli_stmt_close($stmt);

$fee = $doctor['free_consultation'] ? 0.00 : (float) ($type === 'physical' ? $doctor['consultation_fee_physical'] : $doctor['consultation_fee_online']);

$stmt = mysqli_prepare($db, 'INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, consultation_type, status, fee, reason)
    VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?, ?)');
mysqli_stmt_bind_param($stmt, 'iissssds', $patientId, $doctorId, $date, $startTime, $endTime, $type, $fee, $reason);
mysqli_stmt_execute($stmt);
$appointmentId = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

notify_user($doctor['user_id'], 'appointment', 'New appointment request', current_user()['full_name'] . ' requested a ' . $type . ' appointment on ' . format_date($date) . ' at ' . format_time12($startTime) . '.', '/doctor/appointments');
log_activity($_SESSION['user_id'], 'patient', 'book_appointment', "Booked appointment #$appointmentId with doctor #$doctorId");

json_response(true, ['appointment_id' => $appointmentId, 'redirect' => '/patient/appointments'], 'Appointment requested! ' . $doctor['full_name'] . ' will confirm shortly.');
