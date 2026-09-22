<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$schedule = json_decode($_POST['schedule'] ?? '[]', true);
if (!is_array($schedule)) {
    $schedule = [];
}

save_doctor_ticket_schedule($doctorId, $schedule);
log_activity($_SESSION['user_id'], 'doctor', 'save_ticket_schedule', 'Updated ticket queue hours');

json_response(true, [], 'Ticket queue hours saved.');
