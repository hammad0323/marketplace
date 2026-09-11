<?php
require __DIR__ . '/../config/config.php';
require_role_page_or_json('admin');

$id = (int) ($_GET['id'] ?? 0);
$stmt = mysqli_prepare(db(), 'SELECT d.*, u.full_name, u.email, u.phone FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$doctor) {
    json_response(false, [], 'Doctor not found.');
}

$specializationIds = array_map('intval', array_column(get_doctor_specializations($id), 'id'));
$availByDay = get_doctor_availability_by_day($id);
$schedule = [];
foreach ($availByDay as $day => $blocks) {
    foreach ($blocks as $type => $row) {
        $schedule[] = [
            'day_of_week' => $day, 'consultation_type' => $type,
            'start_time' => substr($row['start_time'], 0, 5), 'end_time' => substr($row['end_time'], 0, 5),
            'slot_duration_mins' => (int) $row['slot_duration_mins'],
        ];
    }
}

json_response(true, [
    'doctor' => $doctor,
    'specialization_ids' => $specializationIds,
    'schedule' => $schedule,
]);
