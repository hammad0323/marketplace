<?php
/**
 * POST /api/contact — emails the message to CONTACT_EMAIL.
 * Nothing is written to the database.
 */
header('Content-Type: application/json; charset=utf-8');

function contact_reply($ok, $message, $code = 200)
{
    http_response_code($code);
    echo json_encode(array('ok' => $ok, 'message' => $message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    contact_reply(false, 'Method not allowed.', 405);
}
if (!csrf_check(isset($_POST['csrf']) ? $_POST['csrf'] : '')) {
    contact_reply(false, 'Your session expired. Please reload the page and try again.', 400);
}
// Honeypot: bots fill hidden fields
if (!empty($_POST['website'])) {
    contact_reply(true, 'Thank you — we will get back to you soon.');
}
// Simple rate limit: 5 messages per 10 minutes per session
$now = time();
$_SESSION['contact_times'] = array_values(array_filter(isset($_SESSION['contact_times']) ? $_SESSION['contact_times'] : array(), function ($t) use ($now) {
    return $t > $now - 600;
}));
if (count($_SESSION['contact_times']) >= 5) {
    contact_reply(false, 'You have sent several messages recently. Please wait a few minutes.', 429);
}

$name = trim(isset($_POST['name']) ? (string)$_POST['name'] : '');
$email = trim(isset($_POST['email']) ? (string)$_POST['email'] : '');
$message = trim(isset($_POST['message']) ? (string)$_POST['message'] : '');
$topics = array('general' => 'General question', 'correction' => 'Report incorrect information', 'listing' => 'Add or claim a listing', 'demo' => 'Demo request', 'suggestion' => 'Suggestion');
$topicKey = isset($_POST['topic']) && isset($topics[$_POST['topic']]) ? $_POST['topic'] : 'general';
$page = mb_substr(clean_text(isset($_POST['page']) ? $_POST['page'] : ''), 0, 200, 'UTF-8');

if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
    contact_reply(false, 'Please enter your name.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email, 'UTF-8') > 150) {
    contact_reply(false, 'Please enter a valid email address.', 422);
}
if (mb_strlen($message, 'UTF-8') < 5 || mb_strlen($message, 'UTF-8') > 3000) {
    contact_reply(false, 'Please write a message (5 to 3,000 characters).', 422);
}

// Strip line breaks from header values to prevent header injection
$safeName = preg_replace('/[\r\n]+/', ' ', $name);
$subject = '[The Hashir] ' . $topics[$topicKey] . ' — ' . mb_substr($safeName, 0, 60, 'UTF-8');
$body = "Topic: {$topics[$topicKey]}\nName: {$safeName}\nEmail: {$email}\nPage: " . SITE_URL . $page . "\nIP: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . "\n\n" . $message . "\n";
$headers = array(
    'From: ' . SITE_NAME . ' <' . MAIL_FROM . '>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP',
);
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$sent = @mail(setting('contact_email'), $encodedSubject, $body, implode("\r\n", $headers), '-f' . MAIL_FROM);
if (!$sent) {
    error_log('Contact form mail() failed for ' . $email);
    contact_reply(false, 'We could not send your message right now. Please email ' . CONTACT_EMAIL . ' directly.', 500);
}
$_SESSION['contact_times'][] = $now;
contact_reply(true, 'Thank you, ' . $safeName . ' — we will get back to you soon.');
