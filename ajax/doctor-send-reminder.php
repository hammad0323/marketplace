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

$stmt = mysqli_prepare($db, "SELECT a.*, u.id AS patient_user_id, u.full_name AS patient_name, u.email AS patient_email, du.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = p.user_id
    JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id
    WHERE a.id = ? AND a.doctor_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $appointmentId, $doctorId);
mysqli_stmt_execute($stmt);
$appt = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$appt) {
    json_response(false, [], 'Appointment not found.');
}
if ($appt['status'] !== 'approved') {
    json_response(false, [], 'Reminders can only be sent for confirmed upcoming appointments.');
}
if (!email_enabled()) {
    json_response(false, [], 'Email notifications are turned off in Site Settings.');
}

$typeLabel = $appt['consultation_type'] === 'online' ? 'online video consultation' : 'in-person visit';
$body = '<p>Hi ' . e(explode(' ', $appt['patient_name'])[0]) . ',</p>'
    . '<p>This is a friendly reminder about your upcoming ' . e($typeLabel) . ' with ' . e($appt['doctor_name']) . ':</p>'
    . '<p><strong>Date:</strong> ' . e(format_date($appt['appointment_date'])) . '<br>'
    . '<strong>Time:</strong> ' . e(format_time12($appt['start_time'])) . '</p>';

$sent = send_email($appt['patient_email'], $appt['patient_name'], 'Appointment reminder — ' . format_date($appt['appointment_date']),
    email_template('Appointment Reminder', $body, 'View Appointment', APP_URL . '/patient/appointments'));

if (!$sent) {
    json_response(false, [], 'Could not send the reminder email. Please check the SMTP settings.');
}

// A plain notifications-table insert here (not notify_user()) since that
// helper would also send its own generic email — the nicely-worded
// reminder above already covers that, this just adds the in-app bell alert.
$notifTitle = 'Appointment reminder';
$notifMessage = 'Reminder: your appointment with ' . $appt['doctor_name'] . ' is on ' . format_date($appt['appointment_date']) . ' at ' . format_time12($appt['start_time']) . '.';
$notifLink = '/patient/appointments';
$notifType = 'appointment';
$patientUserId = (int) $appt['patient_user_id'];
$stmt = mysqli_prepare($db, 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'issss', $patientUserId, $notifType, $notifTitle, $notifMessage, $notifLink);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'doctor', 'send_reminder', "Sent reminder email for appointment #$appointmentId");

json_response(true, [], 'Reminder email sent to ' . $appt['patient_name'] . '.');
