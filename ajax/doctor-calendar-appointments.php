<?php
require __DIR__ . '/../config/config.php';
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$year = (int) ($_GET['year'] ?? date('Y'));
$month = (int) ($_GET['month'] ?? date('n'));
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    json_response(false, [], 'Invalid month.');
}

$start = sprintf('%04d-%02d-01', $year, $month);
$end = date('Y-m-d', strtotime($start . ' +1 month'));

$stmt = mysqli_prepare(db(), "
    SELECT a.id, a.appointment_date, a.start_time, a.consultation_type, a.status, u.full_name AS patient_name
    FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = p.user_id
    WHERE a.doctor_id = ? AND a.appointment_date >= ? AND a.appointment_date < ?
    ORDER BY a.appointment_date, a.start_time
");
mysqli_stmt_bind_param($stmt, 'iss', $doctorId, $start, $end);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$counts = [];
$byDate = [];
foreach ($rows as $r) {
    $iso = $r['appointment_date'];
    $counts[$iso] = ($counts[$iso] ?? 0) + 1;
    $byDate[$iso][] = [
        'id' => (int) $r['id'],
        'time' => format_time12($r['start_time']),
        'patient_name' => $r['patient_name'],
        'consultation_type' => $r['consultation_type'],
        'status' => $r['status'],
    ];
}

json_response(true, ['counts' => $counts, 'appointments' => $byDate]);
