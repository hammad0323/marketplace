<?php
/** Polled by the queue panel (doctor + manager) to refresh the ticket list and now-serving numbers without a full reload. */
require __DIR__ . '/../config/config.php';

require_role_page_or_json(['doctor', 'manager']);
$doctorId = resolve_ticket_doctor_id();
if (!$doctorId) {
    json_response(false, [], 'No queue found for this account.');
}

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, [], 'Invalid date.');
}

$counter = mysqli_fetch_assoc(mysqli_query(db(), '
    SELECT last_number, current_serving FROM doctor_ticket_counters
    WHERE doctor_id = ' . (int) $doctorId . " AND ticket_date = '" . mysqli_real_escape_string(db(), $date) . "' LIMIT 1
")) ?: ['last_number' => 0, 'current_serving' => 0];

$stmt = mysqli_prepare(db(), "
    SELECT t.*, u.full_name AS patient_name, u.phone AS patient_phone
    FROM doctor_tickets t
    LEFT JOIN patients p ON p.id = t.patient_id
    LEFT JOIN users u ON u.id = p.user_id
    WHERE t.doctor_id = ? AND t.ticket_date = ?
    ORDER BY t.ticket_number ASC
");
mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
mysqli_stmt_execute($stmt);
$tickets = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$rows = array_map(function ($t) {
    return [
        'id' => (int) $t['id'],
        'number' => (int) $t['ticket_number'],
        'name' => ticket_display_name($t),
        'phone' => $t['patient_phone'] ?: $t['guest_phone'],
        'status' => $t['status'],
        'added_by' => $t['added_by'],
    ];
}, $tickets);

$nowServingName = '';
foreach ($rows as $r) {
    if ($r['number'] === (int) $counter['current_serving']) {
        $nowServingName = $r['name'];
        break;
    }
}

json_response(true, [
    'last_number' => (int) $counter['last_number'],
    'current_serving' => (int) $counter['current_serving'],
    'now_serving_name' => $nowServingName,
    'tickets' => $rows,
]);
