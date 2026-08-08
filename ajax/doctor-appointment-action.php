<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$db = db();
$doctorId = current_profile_id();
$appointmentId = (int) ($_POST['appointment_id'] ?? 0);
$action = $_POST['action'] ?? '';
$note = mb_substr(clean($_POST['note'] ?? ''), 0, 500);

$validActions = ['approve' => 'approved', 'reject' => 'rejected', 'complete' => 'completed', 'no_show' => 'no_show'];
if (!isset($validActions[$action])) {
    json_response(false, [], 'Invalid action.');
}

$stmt = mysqli_prepare($db, 'SELECT a.*, u.id AS patient_user_id, u.full_name AS patient_name FROM appointments a
    JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = p.user_id
    WHERE a.id = ? AND a.doctor_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $appointmentId, $doctorId);
mysqli_stmt_execute($stmt);
$appt = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$appt) {
    json_response(false, [], 'Appointment not found.');
}

$allowedFrom = [
    'approve' => ['pending'],
    'reject' => ['pending'],
    'complete' => ['approved'],
    'no_show' => ['approved'],
];
if (!in_array($appt['status'], $allowedFrom[$action], true)) {
    json_response(false, [], 'This appointment cannot be updated from its current status.');
}

$newStatus = $validActions[$action];
if ($action === 'reject') {
    $stmt = mysqli_prepare($db, "UPDATE appointments SET status = ?, cancelled_by = 'doctor', cancel_reason = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $newStatus, $note, $appointmentId);
} else {
    $stmt = mysqli_prepare($db, 'UPDATE appointments SET status = ?, doctor_notes = ? WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'ssi', $newStatus, $note, $appointmentId);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$messages = [
    'approve' => 'Your appointment on ' . format_date($appt['appointment_date']) . ' was confirmed.',
    'reject' => 'Your appointment request on ' . format_date($appt['appointment_date']) . ' was declined.',
    'complete' => 'Your appointment on ' . format_date($appt['appointment_date']) . ' was marked as completed.',
    'no_show' => 'You were marked as a no-show for your appointment on ' . format_date($appt['appointment_date']) . '.',
];
notify_user($appt['patient_user_id'], 'appointment', 'Appointment update', $messages[$action], '/patient/appointments');
log_activity($_SESSION['user_id'], 'doctor', 'appointment_' . $action, "Appointment #$appointmentId -> $newStatus");

json_response(true, ['status' => $newStatus], 'Appointment updated.');
