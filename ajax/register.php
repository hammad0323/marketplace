<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();

[$success, $message] = register_patient(
    $_POST['full_name'] ?? '',
    $_POST['email'] ?? '',
    $_POST['phone'] ?? '',
    $_POST['password'] ?? ''
);

if (!$success) {
    json_response(false, [], $message);
}

$redirect = get_and_clear_intended_url('/patient/dashboard.php');
json_response(true, ['redirect' => $redirect], $message);
