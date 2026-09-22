<?php
/** Manually set one ticket's status (e.g. mark a no-show, or cancel) — a row-level override alongside the main "Call Next" flow. */
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

$ticketId = (int) ($_POST['ticket_id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!in_array($status, ['waiting', 'completed', 'no_show', 'cancelled'], true)) {
    json_response(false, [], 'Invalid status.');
}

$stmt = mysqli_prepare(db(), 'UPDATE doctor_tickets SET status = ? WHERE id = ? AND doctor_id = ?');
mysqli_stmt_bind_param($stmt, 'sii', $status, $ticketId, $doctorId);
mysqli_stmt_execute($stmt);
$updated = mysqli_stmt_affected_rows($stmt) > 0;
mysqli_stmt_close($stmt);

if (!$updated) {
    json_response(false, [], 'Ticket not found.');
}

log_activity($_SESSION['user_id'], current_role(), 'update_ticket_status', "Set ticket #$ticketId to $status");
json_response(true, [], 'Updated.');
