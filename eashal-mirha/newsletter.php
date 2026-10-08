<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) redirect('');
require_csrf();
$email = mb_substr(post('email'), 0, 190);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $ok = false;
    $msg = 'Please enter a valid email address.';
} else {
    q('INSERT IGNORE INTO subscribers (email) VALUES (?)', [$email]);
    $ok = true;
    $msg = 'Thank you for subscribing — welcome to the inner circle!';
}
if (is_ajax()) json_out(['ok' => $ok, 'message' => $msg]);
flash($ok ? 'success' : 'error', $msg);
back('');
