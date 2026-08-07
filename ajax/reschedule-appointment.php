<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
if (!is_logged_in() || current_role() !== 'patient') {
    json_response(false, [], 'Please log in as a patient to reschedule.');
}

$db = db();
$appointmentId = (int) ($_POST['appointment_id'] ?? 0);
$date = $_POST['date'] ?? '';
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$patientId = current_profile_id();

if ($appointmentId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime)) {
    json_response(false, [], 'Invalid reschedule details.');
}
if (strtotime($date . ' ' . $startTime) < time()) {
    json_response(false, [], 'That time slot is in the past. Please pick another.');
}

$stmt = mysqli_prepare($db, 'SELECT a.*, dp.user_id AS doctor_user_id FROM appointments a JOIN doctors dp ON dp.id = a.doctor_id WHERE a.id = ? AND a.patient_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $appointmentId, $patientId);
mysqli_stmt_execute($stmt);
$appt = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$appt) {
    json_response(false, [], 'Appointment not found.');
}
if (!in_array($appt['status'], ['pending', 'approved'], true)) {
    json_response(false, [], 'Only pending or approved appointments can be rescheduled.');
}

$doctorId = (int) $appt['doctor_id'];
$type = $appt['consultation_type'];

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

$stmt = mysqli_prepare($db, "SELECT id FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND start_time = ? AND status IN ('pending','approved') AND id != ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'issi', $doctorId, $date, $startTime, $appointmentId);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'That slot is already booked. Please pick another.');
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, "UPDATE appointments SET appointment_date = ?, start_time = ?, end_time = ?, status = 'pending' WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'sssi', $date, $startTime, $endTime, $appointmentId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

notify_user($appt['doctor_user_id'], 'appointment', 'Appointment rescheduled', current_user()['full_name'] . ' rescheduled their appointment to ' . format_date($date) . ' at ' . format_time12($startTime) . '. Please re-confirm.', '/doctor/appointments.php');
log_activity($_SESSION['user_id'], 'patient', 'reschedule_appointment', "Rescheduled appointment #$appointmentId");

json_response(true, [], 'Appointment rescheduled. Waiting for the doctor to re-confirm.');
