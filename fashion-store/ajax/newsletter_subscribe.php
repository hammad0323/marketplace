<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
    exit;
}

$stmt = mysqli_prepare($mysqli, "INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)");
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);

echo json_encode(['success' => true, 'message' => 'Thank you for subscribing!']);
