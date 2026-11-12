<?php
// =========================================================
// POST /api/contact.php
// Receives the Contact form from React, checks it,
// and saves it in the "messages" table.
// =========================================================

require_once __DIR__ . '/db.php';
requireMethod('POST');

// 1. Read the JSON that React sent
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJson(['error' => 'Invalid request.'], 400);
}

// 2. Take each field (empty text if missing) and remove extra spaces
$name    = trim((string) ($input['name'] ?? ''));
$email   = trim((string) ($input['email'] ?? ''));
$subject = trim((string) ($input['subject'] ?? ''));
$message = trim((string) ($input['message'] ?? ''));

// 3. Check the data AGAIN on the server.
//    Never trust the browser: anyone can skip the React checks.
$errors = [];

if ($name === '') {
    $errors['name'] = 'Please enter your name.';
} elseif (mb_strlen($name) > 100) {
    $errors['name'] = 'Name is too long (max 100 characters).';
}

if ($email === '') {
    $errors['email'] = 'Please enter your email.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (mb_strlen($subject) > 150) {
    $errors['subject'] = 'Subject is too long (max 150 characters).';
}

if (mb_strlen($message) < 10) {
    $errors['message'] = 'Message should be at least 10 characters.';
} elseif (mb_strlen($message) > 5000) {
    $errors['message'] = 'Message is too long (max 5000 characters).';
}

if (!empty($errors)) {
    sendJson(['errors' => $errors], 422);   // 422 = "data is not valid"
}

// 4. Save it. The ? marks are filled in safely by PDO (prepared statement).
//    This protects the database from SQL injection attacks.
try {
    $stmt = getDB()->prepare(
        'INSERT INTO messages (name, email, subject, message) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $subject, $message]);
} catch (PDOException $e) {
    error_log('contact.php: ' . $e->getMessage());
    sendJson(['error' => DEBUG ? $e->getMessage() : 'Could not save your message.'], 500);
}

// 5. Tell React it worked
sendJson([
    'success' => true,
    'id'      => (int) getDB()->lastInsertId(),
], 201);   // 201 = "created"
