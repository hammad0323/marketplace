<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();

if (!is_logged_in()) {
    json_response(false, ['auth_required' => true], 'Please log in to get a ticket.');
}
if (current_role() !== 'patient') {
    json_response(false, [], 'Only patient accounts can book a ticket.');
}

$db = db();
$doctorId = (int) ($_POST['doctor_id'] ?? 0);
$date = $_POST['date'] ?? '';
$notes = mb_substr(clean($_POST['notes'] ?? ''), 0, 255);
$patientId = current_profile_id();

if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$patientId) {
    json_response(false, [], 'Invalid booking details. Please try again.');
}
if (strtotime($date) < strtotime(date('Y-m-d'))) {
    json_response(false, [], 'That date has already passed.');
}

$stmt = mysqli_prepare($db, "SELECT d.id, u.id AS user_id, u.full_name
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.id = ? AND d.booking_mode = 'tickets' AND d.verification_status = 'verified' AND u.status = 'active' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if (!$doctor) {
    json_response(false, [], 'This doctor is not available for ticket booking.');
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
$stmt = mysqli_prepare($db, 'SELECT id FROM doctor_ticket_schedule WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1 LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $doctorId, $dayOfWeek);
mysqli_stmt_execute($stmt);
if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'There is no ticket queue on this date. Please pick another day.');
}
mysqli_stmt_close($stmt);

// One ticket per patient per doctor per day — stops accidental double-booking
// (e.g. a double click) without blocking a genuinely new visit on another day.
$stmt = mysqli_prepare($db, "SELECT ticket_number FROM doctor_tickets WHERE doctor_id = ? AND patient_id = ? AND ticket_date = ? AND status NOT IN ('cancelled') LIMIT 1");
mysqli_stmt_bind_param($stmt, 'iis', $doctorId, $patientId, $date);
mysqli_stmt_execute($stmt);
$existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if ($existing) {
    json_response(false, [], 'You already have ticket #' . $existing['ticket_number'] . ' for this date.');
}

mysqli_begin_transaction($db);
try {
    $ticketNumber = allocate_ticket_number($doctorId, $date);
    $stmt = mysqli_prepare($db, "INSERT INTO doctor_tickets (doctor_id, patient_id, ticket_date, ticket_number, added_by, notes) VALUES (?, ?, ?, ?, 'patient', ?)");
    mysqli_stmt_bind_param($stmt, 'iisis', $doctorId, $patientId, $date, $ticketNumber, $notes);
    mysqli_stmt_execute($stmt);
    $ticketId = mysqli_insert_id($db);
    mysqli_stmt_close($stmt);
    mysqli_commit($db);
} catch (Exception $e) {
    mysqli_rollback($db);
    error_log('book-ticket failed: ' . $e->getMessage());
    json_response(false, [], 'Could not book your ticket. Please try again.');
}

$patientName = current_user()['full_name'];
$dateLabel = format_date($date);

notify_user((int) $doctor['user_id'], 'ticket', 'New ticket booked', $patientName . ' booked ticket #' . $ticketNumber . ' for ' . $dateLabel . '.', '/doctor/queue?date=' . $date);
notify_user((int) $_SESSION['user_id'], 'ticket', 'Your ticket is confirmed', 'Ticket #' . $ticketNumber . ' with ' . $doctor['full_name'] . ' on ' . $dateLabel . '. First-come-first-served — check the doctor\'s page on the day for the current number being served.', '/patient/tickets');

log_activity($_SESSION['user_id'], 'patient', 'book_ticket', "Booked ticket #$ticketNumber with doctor #$doctorId for $date");

json_response(true, ['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber, 'date' => $date], 'Your ticket number is #' . $ticketNumber . ' for ' . $dateLabel . '.');
