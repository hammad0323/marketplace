<?php
require __DIR__ . '/config.php';

$back = $_SERVER['HTTP_REFERER'] ?? url();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $email = trim((string) ($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        q('INSERT IGNORE INTO subscribers (email) VALUES (?)', [$email]);
        $_SESSION['subscribed'] = 1;
    }
}
// Only redirect back to our own site.
$host = parse_url($back, PHP_URL_HOST);
if ($host && $host !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
    $back = url();
}
header('Location: ' . strtok($back, '#') . '#subscribed');
exit;
