<?php
/**
 * Email Engine - dynamic templates, queued delivery, variable substitution.
 */
require_once __DIR__ . '/db.php';

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
    $vars = array_merge(['platform_name' => APP_NAME, 'login_url' => base_url('login.php')], $vars);
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

/**
 * Attempts immediate delivery of one queued email; falls back gracefully (logs) when mail() unavailable,
 * which is the common case in sandboxed/dev environments without an MTA.
 */
function deliver_email(array $queueRow): bool
{
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . APP_NAME . " <no-reply@" . (parse_url(base_url(), PHP_URL_HOST) ?: 'qualitycore.app') . ">\r\n";
    $sent = false;
    if (function_exists('mail')) {
        $sent = @mail($queueRow['to_email'], $queueRow['subject'], $queueRow['body_html'], $headers);
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
