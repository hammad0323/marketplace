<?php
/** Newsletter subscribe with explicit consent, honeypot and rate limiting. */
if (!defined('EBAYA')) { http_response_code(403); exit; }
if (!is_post()) json_out(['ok' => false], 405);
csrf_check();
if (post('website') !== '') json_out(['ok' => true, 'message' => 'Thank you for subscribing.']);
rate_limit_or_fail('newsletter', 5, 3600);
$email = strtolower(mb_substr(post('email'), 0, 190));
if (!v_email($email)) json_out(['ok' => false, 'message' => 'Please enter a valid email address.']);
if (!post('consent')) json_out(['ok' => false, 'message' => 'Please tick the consent box to subscribe.']);
$sec = db_one("SELECT settings FROM homepage_sections WHERE section_key = 'testimonials_newsletter'");
$consent = homepage_settings_merge('testimonials_newsletter', json_decode((string)($sec['settings'] ?? ''), true) ?: [])['n_consent'];
$existing = db_one('SELECT * FROM newsletter_subscribers WHERE email = ?', [$email]);
if ($existing && $existing['status'] === 'subscribed') json_out(['ok' => true, 'message' => 'You are already subscribed — thank you!']);
if ($existing) {
    db_exec("UPDATE newsletter_subscribers SET status = 'subscribed', consent_text = ?, consent_at = NOW(), consent_ip = ?, unsubscribed_at = NULL WHERE id = ?", [$consent, client_ip(), (int)$existing['id']]);
} else {
    db_insert('INSERT INTO newsletter_subscribers (email, consent_text, consent_at, consent_ip, unsubscribe_token, source) VALUES (?, ?, NOW(), ?, ?, ?)', [$email, $consent, client_ip(), random_token(32), 'homepage']);
}
json_out(['ok' => true, 'message' => 'Welcome to the Ebaya Circle.']);
