<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$admin = current_user();
$body = '<p>This is a test email from your ' . e(get_setting('site_name', SITE_NAME)) . ' admin settings. '
    . 'If you received this, your SMTP configuration is working correctly.</p>';

$sent = send_email($admin['email'], $admin['full_name'], 'Test Email — ' . get_setting('site_name', SITE_NAME), email_template('SMTP is working!', $body), true);

if ($sent) {
    json_response(true, [], 'Test email sent to ' . $admin['email'] . '.');
}
json_response(false, [], 'Could not send test email. Check your SMTP settings and the server error log for details.');
