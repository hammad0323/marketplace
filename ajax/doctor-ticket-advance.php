<?php
/** "Call Next" — advances current_serving by one, completing the ticket just finished and marking the new number as being served. */
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json(['doctor', 'manager']);
$doctorId = resolve_ticket_doctor_id();
if (!$doctorId) {
    json_response(false, [], 'No queue found for this account.');
}

$date = $_POST['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, [], 'Invalid date.');
}

$db = db();
mysqli_begin_transaction($db);
try {
    $stmt = mysqli_prepare($db, 'INSERT INTO doctor_ticket_counters (doctor_id, ticket_date) VALUES (?, ?) ON DUPLICATE KEY UPDATE doctor_id = doctor_id');
    mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db, 'SELECT last_number, current_serving FROM doctor_ticket_counters WHERE doctor_id = ? AND ticket_date = ? FOR UPDATE');
    mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
    mysqli_stmt_execute($stmt);
    $counter = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    $current = (int) $counter['current_serving'];
    $last = (int) $counter['last_number'];
    if ($current >= $last) {
        mysqli_rollback($db);
        json_response(false, [], 'No more tickets waiting.');
    }

    if ($current > 0) {
        $stmt = mysqli_prepare($db, "UPDATE doctor_tickets SET status = 'completed' WHERE doctor_id = ? AND ticket_date = ? AND ticket_number = ? AND status IN ('serving', 'waiting')");
        mysqli_stmt_bind_param($stmt, 'isi', $doctorId, $date, $current);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $next = $current + 1;
    $stmt = mysqli_prepare($db, 'UPDATE doctor_ticket_counters SET current_serving = ? WHERE doctor_id = ? AND ticket_date = ?');
    mysqli_stmt_bind_param($stmt, 'iis', $next, $doctorId, $date);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db, "UPDATE doctor_tickets SET status = 'serving' WHERE doctor_id = ? AND ticket_date = ? AND ticket_number = ? AND status = 'waiting'");
    mysqli_stmt_bind_param($stmt, 'isi', $doctorId, $date, $next);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($db);
} catch (Exception $e) {
    mysqli_rollback($db);
    error_log('doctor-ticket-advance failed: ' . $e->getMessage());
    json_response(false, [], 'Could not advance the queue. Please try again.');
}

log_activity($_SESSION['user_id'], current_role(), 'advance_ticket_queue', "Advanced doctor #$doctorId queue to ticket #$next on $date");

json_response(true, ['current_serving' => $next], 'Now serving #' . $next . '.');
