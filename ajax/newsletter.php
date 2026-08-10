<?php
require_once __DIR__ . '/../config/config.php';

$redirectTo = $_POST['redirect'] ?? '/index.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}
verify_csrf();

$email = filter_var(clean_input($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$email) {
    flash_set('danger', 'Please enter a valid email address.');
    redirect($redirectTo);
}

$existing = db_select_one($conn, 'SELECT id FROM newsletter_subscribers WHERE email = ?', [$email]);
if (!$existing) {
    db_execute($conn, 'INSERT INTO newsletter_subscribers (email) VALUES (?)', [$email]);
}

flash_set('success', "You're subscribed! We'll send trip ideas to {$email}.");
redirect($redirectTo);
