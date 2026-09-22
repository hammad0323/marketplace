<?php
/**
 * Public: is a doctor's ticket queue open on a given date, and (if it's
 * today) what's the live queue status? Mirrors check-availability.php's
 * role for the slot-based system — the ticket booking widget calls this
 * after a date is picked on the calendar, before showing the "Get My
 * Ticket" button.
 */
require __DIR__ . '/../config/config.php';

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';

if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, [], 'Invalid request.');
}
if (strtotime($date) < strtotime(date('Y-m-d'))) {
    json_response(false, [], 'That date has already passed.');
}

$stmt = mysqli_prepare(db(), "SELECT id FROM doctors WHERE id = ? AND booking_mode = 'tickets' AND verification_status = 'verified' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'This doctor does not use ticket booking.');
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(db(), 'SELECT id FROM doctor_blocked_dates WHERE doctor_id = ? AND blocked_date = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(true, ['open' => false, 'message' => 'The doctor is unavailable on this date.']);
}
mysqli_stmt_close($stmt);

$dayOfWeek = (int) date('w', strtotime($date));
$stmt = mysqli_prepare(db(), 'SELECT start_time, end_time FROM doctor_ticket_schedule WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1 LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $doctorId, $dayOfWeek);
mysqli_stmt_execute($stmt);
$hours = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$hours) {
    json_response(true, ['open' => false, 'message' => 'No ticket queue on this day. Please pick another date.']);
}

$stmt = mysqli_prepare(db(), 'SELECT last_number, current_serving FROM doctor_ticket_counters WHERE doctor_id = ? AND ticket_date = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
mysqli_stmt_execute($stmt);
$counter = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

json_response(true, [
    'open' => true,
    'start_time' => format_time12($hours['start_time']),
    'end_time' => format_time12($hours['end_time']),
    'last_number' => (int) ($counter['last_number'] ?? 0),
    'current_serving' => (int) ($counter['current_serving'] ?? 0),
]);
