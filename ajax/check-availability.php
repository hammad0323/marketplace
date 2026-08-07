<?php
require __DIR__ . '/../config/config.php';

$doctorId = (int) ($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';
$type = $_GET['type'] ?? 'online';

if ($doctorId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, [], 'Invalid request.');
}
if (strtotime($date) < strtotime(date('Y-m-d'))) {
    json_response(true, ['blocked' => true, 'slots' => []], 'That date has already passed.');
}

$stmt = mysqli_prepare(db(), "SELECT id FROM doctors WHERE id = ? AND verification_status = 'verified' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
if (!mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, [], 'Doctor not found.');
}
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(db(), 'SELECT id FROM doctor_blocked_dates WHERE doctor_id = ? AND blocked_date = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(true, ['blocked' => true, 'slots' => []], 'The doctor is unavailable on this date.');
}
mysqli_stmt_close($stmt);

$dayOfWeek = (int) date('w', strtotime($date));
$stmt = mysqli_prepare(db(), "SELECT start_time, end_time, slot_duration_mins FROM doctor_availability
    WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1 AND (consultation_type = ? OR consultation_type = 'both')");
mysqli_stmt_bind_param($stmt, 'iis', $doctorId, $dayOfWeek, $type);
mysqli_stmt_execute($stmt);
$windows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

if (!$windows) {
    json_response(true, ['blocked' => false, 'slots' => []], 'No availability on this date.');
}

$stmt = mysqli_prepare(db(), "SELECT start_time FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending','approved')");
mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
mysqli_stmt_execute($stmt);
$booked = array_column(mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC), 'start_time');
mysqli_stmt_close($stmt);

$isToday = $date === date('Y-m-d');
$now = time();
$slots = [];
foreach ($windows as $w) {
    $cursor = strtotime($date . ' ' . $w['start_time']);
    $end = strtotime($date . ' ' . $w['end_time']);
    $step = max(5, (int) $w['slot_duration_mins']) * 60;
    while ($cursor + $step <= $end) {
        $slotStart = date('H:i:s', $cursor);
        $slotEndStr = date('H:i:s', $cursor + $step);
        $slots[] = [
            'start' => $slotStart,
            'end' => $slotEndStr,
            'label' => date('g:i A', $cursor),
            'available' => !in_array($slotStart, $booked, true) && !($isToday && $cursor < $now),
        ];
        $cursor += $step;
    }
}

json_response(true, ['blocked' => false, 'slots' => $slots]);
