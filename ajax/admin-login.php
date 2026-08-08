<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();

[$success, $message, $userRow] = attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '');

if (!$success) {
    json_response(false, [], $message);
}
if ($userRow['role'] !== 'admin') {
    logout_user();
    session_start();
    json_response(false, [], 'This login is for administrators only.');
}

$redirect = get_and_clear_intended_url('/admin/dashboard');
json_response(true, ['redirect' => $redirect], $message);
