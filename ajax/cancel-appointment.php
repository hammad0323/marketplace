<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
if (!is_logged_in() || !in_array(current_role(), ['patient', 'doctor'], true)) {
    json_response(false, [], 'Please log in to manage appointments.');
}

$db = db();
$appointmentId = (int) ($_POST['appointment_id'] ?? 0);
$reason = mb_substr(clean($_POST['cancel_reason'] ?? ''), 0, 255);
$role = current_role();
$profileId = current_profile_id();

$stmt = mysqli_prepare($db, 'SELECT a.*, dp.user_id AS doctor_user_id, pp.user_id AS patient_user_id
    FROM appointments a
    JOIN doctors dp ON dp.id = a.doctor_id
    JOIN patients pp ON pp.id = a.patient_id
    WHERE a.id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $appointmentId);
mysqli_stmt_execute($stmt);
$appt = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$appt) {
    json_response(false, [], 'Appointment not found.');
}
$owns = ($role === 'patient' && (int) $appt['patient_id'] === $profileId) || ($role === 'doctor' && (int) $appt['doctor_id'] === $profileId);
if (!$owns) {
    json_response(false, [], 'You do not have permission to cancel this appointment.');
}
if (!in_array($appt['status'], ['pending', 'approved'], true)) {
    json_response(false, [], 'Only pending or approved appointments can be cancelled.');
}

$stmt = mysqli_prepare($db, "UPDATE appointments SET status = 'cancelled', cancelled_by = ?, cancel_reason = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'ssi', $role, $reason, $appointmentId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$notifyUserId = $role === 'patient' ? $appt['doctor_user_id'] : $appt['patient_user_id'];
notify_user($notifyUserId, 'appointment', 'Appointment cancelled', 'The appointment on ' . format_date($appt['appointment_date']) . ' at ' . format_time12($appt['start_time']) . ' was cancelled.', $role === 'patient' ? '/doctor/appointments.php' : '/patient/appointments.php');
log_activity($_SESSION['user_id'], $role, 'cancel_appointment', "Cancelled appointment #$appointmentId");

json_response(true, [], 'Appointment cancelled.');
