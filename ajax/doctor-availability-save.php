<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$rows = json_decode($_POST['schedule'] ?? '[]', true);

if (!is_array($rows)) {
    json_response(false, [], 'Invalid schedule data.');
}

if (!save_doctor_availability($doctorId, $rows)) {
    json_response(false, [], 'Could not save availability. Please try again.');
}

log_activity($_SESSION['user_id'], 'doctor', 'update_availability', 'Updated weekly availability');
json_response(true, [], 'Availability updated.');
