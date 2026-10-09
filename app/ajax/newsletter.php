<?php
if (!is_post()) {
    json_response(['ok' => false], 405);
}
require_csrf();
if (input('website') !== '') {
    json_response(['ok' => true, 'message' => 'Thank you for subscribing.']);
}
if (!rate_limit('newsletter', client_ip(), 5, 3600)) {
    json_response(['ok' => false, 'message' => 'Too many attempts. Please try again later.'], 429);
}
if (!input_bool('consent')) {
    json_response(['ok' => false, 'message' => 'Please tick the consent box to subscribe.'], 422);
}
// Consent wording is taken from the server-side section settings, not from the request.
$row = db_one("SELECT settings FROM homepage_sections WHERE section_type = 'newsletter' LIMIT 1");
$s = array_merge(section_defaults('newsletter'), json_decode((string) ($row['settings'] ?? ''), true) ?: []);
$result = newsletter_subscribe(input('email'), $s['consent_text'], in_array(input('source'), ['homepage', 'footer', 'register'], true) ? input('source') : 'footer');
if ($result === 'invalid') {
    json_response(['ok' => false, 'message' => 'Please enter a valid email address.'], 422);
}
json_response(['ok' => true, 'message' => $result === 'already' ? 'You are already subscribed — thank you!' : 'Thank you for subscribing. Welcome to the circle.']);
