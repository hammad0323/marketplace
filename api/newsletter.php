<?php
/** POST /api/newsletter email= */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
if (!is_post()) json_error('Method not allowed', 405);
csrf_check();
$email = mb_strtolower(input('email'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Please enter a valid email address.');
if (!form_throttle('newsletter', 10)) json_error('Please wait a few seconds and try again.', 429);
db_exec('INSERT INTO newsletter_subscribers (email) VALUES (?) ON DUPLICATE KEY UPDATE is_active = 1', [$email]);
json_out(['ok' => true, 'message' => 'Thank you! You are subscribed to Fahim Store deals.']);
