<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$db = db();
$doctorId = current_profile_id();
$rows = json_decode($_POST['schedule'] ?? '[]', true);

if (!is_array($rows)) {
    json_response(false, [], 'Invalid schedule data.');
}

mysqli_begin_transaction($db);
try {
    $stmt = mysqli_prepare($db, 'DELETE FROM doctor_availability WHERE doctor_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $insert = mysqli_prepare($db, 'INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, slot_duration_mins, consultation_type) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($rows as $row) {
        $day = (int) ($row['day_of_week'] ?? -1);
        $start = $row['start_time'] ?? '';
        $end = $row['end_time'] ?? '';
        $duration = max(5, (int) ($row['slot_duration_mins'] ?? 30));
        $type = in_array($row['consultation_type'] ?? '', ['online', 'physical', 'both'], true) ? $row['consultation_type'] : 'both';

        if ($day < 0 || $day > 6 || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) {
            continue;
        }
        if (strtotime($start) >= strtotime($end)) {
            continue;
        }
        mysqli_stmt_bind_param($insert, 'iissis', $doctorId, $day, $start, $end, $duration, $type);
        mysqli_stmt_execute($insert);
    }
    mysqli_stmt_close($insert);
    mysqli_commit($db);
} catch (Exception $e) {
    mysqli_rollback($db);
    error_log('doctor-availability-save failed: ' . $e->getMessage());
    json_response(false, [], 'Could not save availability. Please try again.');
}

log_activity($_SESSION['user_id'], 'doctor', 'update_availability', 'Updated weekly availability');
json_response(true, [], 'Availability updated.');
