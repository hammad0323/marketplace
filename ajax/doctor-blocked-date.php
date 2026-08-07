<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$db = db();
$doctorId = current_profile_id();
$op = $_POST['op'] ?? 'add';

if ($op === 'remove') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($db, 'DELETE FROM doctor_blocked_dates WHERE id = ? AND doctor_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $doctorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    json_response(true, [], 'Blocked date removed.');
}

$date = $_POST['date'] ?? '';
$reason = mb_substr(clean($_POST['reason'] ?? ''), 0, 255);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) < strtotime(date('Y-m-d'))) {
    json_response(false, [], 'Please choose a valid upcoming date.');
}

$stmt = mysqli_prepare($db, 'INSERT IGNORE INTO doctor_blocked_dates (doctor_id, blocked_date, reason) VALUES (?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'iss', $doctorId, $date, $reason);
mysqli_stmt_execute($stmt);
$id = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

json_response(true, ['id' => $id, 'date' => format_date($date), 'reason' => e($reason)], 'Date blocked.');
