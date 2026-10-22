<?php
require __DIR__ . '/_init.php';
ajax_require_post();
if (!rate_limit('news:' . client_ip(), 8, 3600)) {
    json_response(['ok' => false, 'message' => 'Too many attempts. Please try again later.'], 429);
}
$email = strtolower(input('email', '', 'post'));
if (!valid_email($email)) {
    json_response(['ok' => false, 'message' => 'Please enter a valid email address.'], 422);
}
if (!input_bool('consent')) {
    json_response(['ok' => false, 'message' => 'Please tick the box to confirm you would like to receive our emails.'], 422);
}
$source = preg_replace('/[^a-z]/', '', input('source', 'web', 'post')) ?: 'web';
$res = newsletter_subscribe($email, $source, 'I agree to receive marketing emails from ' . store_name() . '.');
json_response(['ok' => true, 'message' => $res === 'already' ? 'You are already subscribed — thank you!' : 'Thank you for subscribing!']);
