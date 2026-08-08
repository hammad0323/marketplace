<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$enabled = !empty($_POST['chat_enabled']) ? 1 : 0;
$visibleToGuests = !empty($_POST['chat_visible_to_guests']) ? 1 : 0;
$startTime = $_POST['chat_start_time'] ?? '';
$endTime = $_POST['chat_end_time'] ?? '';

$startTime = preg_match('/^\d{2}:\d{2}$/', $startTime) ? $startTime . ':00' : null;
$endTime = preg_match('/^\d{2}:\d{2}$/', $endTime) ? $endTime . ':00' : null;
if (!$startTime || !$endTime) {
    $startTime = $endTime = null;
}

$stmt = mysqli_prepare(db(), 'UPDATE doctors SET chat_enabled = ?, chat_visible_to_guests = ?, chat_start_time = ?, chat_end_time = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'iissi', $enabled, $visibleToGuests, $startTime, $endTime, $doctorId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'doctor', 'update_chat_settings', 'Updated messaging availability settings');
json_response(true, [], 'Messaging settings saved.');
