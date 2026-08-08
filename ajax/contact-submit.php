<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();

$name = clean($_POST['name'] ?? '');
$email = clean($_POST['email'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$subject = clean($_POST['subject'] ?? '');
$message = clean($_POST['message'] ?? '');

$errors = [];
if ($name === '') $errors['name'] = 'Name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'A valid email is required.';
if ($subject === '') $errors['subject'] = 'Subject is required.';
if (mb_strlen($message) < 10) $errors['message'] = 'Message must be at least 10 characters.';

if ($errors) {
    json_response(false, ['errors' => $errors], 'Please fix the errors below.');
}

$stmt = mysqli_prepare(db(), 'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'sssss', $name, $email, $phone, $subject, $message);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

notify_admins('contact', 'New contact message: ' . $subject, $name . ' (' . $email . ') wrote: ' . excerpt($message, 200), '/admin/messages');

json_response(true, [], 'Thanks for reaching out! We\'ll get back to you within one business day.');
