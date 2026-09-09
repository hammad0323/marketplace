<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();

if (is_logged_in()) {
    json_response(true, [], 'Already signed in.');
}

$contact = clean($_POST['contact'] ?? '');
[$ok, $message, , $password] = find_or_create_guest_patient($contact);
if (!$ok) {
    json_response(false, [], $message);
}

json_response(true, ['password' => $password], $message);
