<?php
/** Doctor/manager adds a walk-in patient directly to today's (or a chosen date's) queue — no account needed. */
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
$name = mb_substr(clean($_POST['name'] ?? ''), 0, 150);
$phone = mb_substr(clean($_POST['phone'] ?? ''), 0, 30) ?: null;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    json_response(false, [], 'Invalid date.');
}
if ($name === '') {
    json_response(false, ['errors' => ['name' => 'Please enter the patient\'s name.']], 'Please fix the errors below.');
}

$db = db();
$addedBy = current_role() === 'manager' ? 'manager' : 'doctor';

mysqli_begin_transaction($db);
try {
    $ticketNumber = allocate_ticket_number($doctorId, $date);
    $stmt = mysqli_prepare($db, 'INSERT INTO doctor_tickets (doctor_id, guest_name, guest_phone, ticket_date, ticket_number, added_by) VALUES (?, ?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isssis', $doctorId, $name, $phone, $date, $ticketNumber, $addedBy);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($db);
} catch (Exception $e) {
    mysqli_rollback($db);
    error_log('doctor-ticket-add-walkin failed: ' . $e->getMessage());
    json_response(false, [], 'Could not add the ticket. Please try again.');
}

log_activity($_SESSION['user_id'], current_role(), 'add_walkin_ticket', "Added walk-in ticket #$ticketNumber for doctor #$doctorId on $date");

json_response(true, ['ticket_number' => $ticketNumber], 'Added — ticket #' . $ticketNumber . '.');
