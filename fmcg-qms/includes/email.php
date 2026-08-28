<?php
/**
 * Email Engine - dynamic templates, queued delivery, variable substitution.
 * Delivers via real SMTP when configured (Platform Settings > Email Settings); falls back
 * to PHP's mail() otherwise (works if the host has a local MTA, e.g. sendmail/postfix).
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp.php';

function render_template_string(string $content, array $vars): string
{
    foreach ($vars as $key => $val) {
        $content = str_replace('{{' . $key . '}}', (string)$val, $content);
    }
    return $content;
}

function get_email_template(string $eventKey, ?int $companyId = null): ?array
{
    $tpl = null;
    if ($companyId) {
        $tpl = db_fetch_one("SELECT * FROM email_templates WHERE event_key = ? AND company_id = ? LIMIT 1", 'si', [$eventKey, $companyId]);
    }
    if (!$tpl) {
        $tpl = db_fetch_one("SELECT * FROM email_templates WHERE event_key = ? AND company_id IS NULL LIMIT 1", 's', [$eventKey]);
    }
    return $tpl;
}

/**
 * Queues an email for the given event using its template. Vars support {{employee_name}}, {{company_name}}, etc.
 */
function queue_email(?int $companyId, string $toEmail, string $toName, string $eventKey, array $vars = []): bool
{
    $tpl = get_email_template($eventKey, $companyId);
    if (!$tpl) {
        return false;
    }
    $vars = array_merge(['platform_name' => app_name(), 'login_url' => app_absolute_url('login.php')], $vars);
    $subject = render_template_string($tpl['subject'], $vars);
    $body = render_template_string($tpl['body_html'], $vars);
    $result = db_execute(
        "INSERT INTO email_queue (company_id, to_email, to_name, subject, body_html, event_key, status, created_at)
         VALUES (?,?,?,?,?,?, 'pending', NOW())",
        'isssss',
        [$companyId, $toEmail, $toName, $subject, $body, $eventKey]
    );
    return $result !== false;
}

function smtp_config_from_settings(): array
{
    return [
        'host' => get_platform_setting('smtp_host', ''),
        'port' => (int)get_platform_setting('smtp_port', 587),
        'secure' => get_platform_setting('smtp_secure', 'tls'),
        'username' => get_platform_setting('smtp_username', ''),
        'password' => get_platform_setting('smtp_password', ''),
        'from_email' => get_platform_setting('smtp_from_email', '') ?: ('no-reply@' . (parse_url(app_absolute_url(), PHP_URL_HOST) ?: 'localhost')),
        'from_name' => get_platform_setting('smtp_from_name', '') ?: app_name(),
    ];
}

/**
 * Attempts immediate delivery of one queued email. Uses SMTP if a host is configured
 * (Platform Settings > Email Settings), otherwise falls back to PHP's mail().
 */
function deliver_email(array $queueRow): bool
{
    $smtpConfig = smtp_config_from_settings();
    $sent = false;
    $error = null;

    if ($smtpConfig['host'] !== '') {
        $sent = smtp_send($smtpConfig, $queueRow['to_email'], $queueRow['to_name'] ?: $queueRow['to_email'], $queueRow['subject'], $queueRow['body_html'], $error);
        if (!$sent) {
            error_log('SMTP delivery failed for ' . $queueRow['to_email'] . ': ' . $error);
        }
    } else {
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $headers .= 'From: ' . $smtpConfig['from_name'] . ' <' . $smtpConfig['from_email'] . ">\r\n";
        if (function_exists('mail')) {
            $sent = @mail($queueRow['to_email'], $queueRow['subject'], $queueRow['body_html'], $headers);
        }
    }

    $status = $sent ? 'sent' : 'failed';
    db_execute("UPDATE email_queue SET status = ?, sent_at = NOW(), attempts = attempts + 1 WHERE id = ?", 'si', [$status, $queueRow['id']]);
    db_execute(
        "INSERT INTO email_logs (company_id, to_email, subject, event_key, status, created_at) VALUES (?,?,?,?,?,NOW())",
        'issss',
        [$queueRow['company_id'], $queueRow['to_email'], $queueRow['subject'], $queueRow['event_key'], $status]
    );
    return $sent;
}

function process_pending_emails(int $limit = 50): int
{
    $rows = db_fetch_all("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 3 ORDER BY created_at ASC LIMIT ?", 'i', [$limit]);
    $count = 0;
    foreach ($rows as $row) {
        if (deliver_email($row)) {
            $count++;
        }
    }
    return $count;
}

/** Convenience wrapper: queue + attempt immediate delivery (best-effort, non-blocking on failure). */
function send_event_email(?int $companyId, string $toEmail, string $toName, string $eventKey, array $vars = []): void
{
    if (queue_email($companyId, $toEmail, $toName, $eventKey, $vars)) {
        $row = db_fetch_one("SELECT * FROM email_queue WHERE to_email = ? AND event_key = ? ORDER BY id DESC LIMIT 1", 'ss', [$toEmail, $eventKey]);
        if ($row) {
            deliver_email($row);
        }
    }
}

/** Sends an immediate, unqueued test email using the current SMTP configuration. Returns an error message, or null on success. */
function send_test_email(string $toEmail): ?string
{
    $smtpConfig = smtp_config_from_settings();
    if ($smtpConfig['host'] === '') {
        if (!function_exists('mail') || !@mail($toEmail, 'Test Email from ' . app_name(), '<p>This is a test email from ' . out(app_name()) . '. If you received this, mail() delivery is working.</p>',
            "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: " . $smtpConfig['from_name'] . ' <' . $smtpConfig['from_email'] . ">\r\n")) {
            return 'No SMTP host is configured and PHP mail() is unavailable or failed. Configure SMTP below.';
        }
        return null;
    }
    $error = null;
    $sent = smtp_send($smtpConfig, $toEmail, $toEmail, 'Test Email from ' . app_name(),
        '<p>This is a test email from <strong>' . out(app_name()) . '</strong> sent via your configured SMTP server (' . out_plain($smtpConfig['host']) . ').</p><p>If you received this, email delivery is working correctly.</p>',
        $error);
    return $sent ? null : ($error ?: 'Unknown SMTP error.');
}
