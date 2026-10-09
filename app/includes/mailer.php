<?php
/**
 * Outgoing email: PHP mail() or a minimal authenticated SMTP client
 * (STARTTLS / SSL). Templates live in app/views/emails/.
 */

function send_template_email(string $to, string $subject, string $template, array $vars = []): bool
{
    if (!valid_email($to)) {
        return false;
    }
    if (!setting_bool('email_enabled', true)) {
        db_insert('email_log', ['recipient' => $to, 'subject' => mb_substr($subject, 0, 255), 'template' => $template, 'status' => 'skipped', 'error' => 'Email disabled in settings']);
        return false;
    }
    $file = APP_PATH . '/views/emails/' . basename($template) . '.php';
    if (!is_file($file)) {
        return false;
    }
    ob_start();
    extract($vars, EXTR_SKIP);
    $email_subject = $subject;
    require APP_PATH . '/views/emails/_layout_top.php';
    require $file;
    require APP_PATH . '/views/emails/_layout_bottom.php';
    $html = (string) ob_get_clean();
    return send_email($to, $subject, $html, $template);
}

function send_email(string $to, string $subject, string $html, ?string $template = null): bool
{
    $from = defined('MAIL_FROM') ? MAIL_FROM : setting('support_email', '');
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : setting('site_name', 'Beglet');
    $text = trim(html_entity_decode(strip_tags(preg_replace(['#<br\s*/?>#i', '#</(p|div|tr|h[1-6])>#i'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $text));
    $boundary = 'b' . bin2hex(random_bytes(8));
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'MIME-Version: 1.0',
        'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        'Reply-To: ' . (setting('support_email', '') ?: $from),
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: Beglet',
    ];
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";

    $error = null;
    try {
        if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'smtp') {
            smtp_send($from, $to, "Subject: $encSubject\r\nTo: <$to>\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . '@' . (parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost') . ">\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body);
            $ok = true;
        } else {
            $ok = @mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $from);
            if (!$ok) {
                $error = 'mail() returned false';
            }
        }
    } catch (Throwable $e) {
        $ok = false;
        $error = $e->getMessage();
    }
    try {
        db_insert('email_log', ['recipient' => $to, 'subject' => mb_substr($subject, 0, 255), 'template' => $template, 'status' => $ok ? 'sent' : 'failed', 'error' => $error ? mb_substr($error, 0, 500) : null]);
    } catch (Throwable $e) {
        // logging must never break checkout
    }
    return $ok;
}

function smtp_send(string $from, string $to, string $data): void
{
    $secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
    $host = ($secure === 'ssl' ? 'ssl://' : '') . SMTP_HOST;
    $fp = @stream_socket_client($host . ':' . SMTP_PORT, $errno, $errstr, 15);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: $errstr");
    }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = function (string $c, array $expect) use ($fp, $read) {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int) substr($r, 0, 3), $expect, true)) {
            throw new RuntimeException('SMTP error after "' . explode(' ', $c)[0] . '": ' . trim($r));
        }
        return $r;
    };
    $read();
    $ehlo = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
    $cmd("EHLO $ehlo", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('SMTP STARTTLS failed');
        }
        $cmd("EHLO $ehlo", [250]);
    }
    if (SMTP_USER !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(SMTP_USER), [334]);
        $cmd(base64_encode(SMTP_PASS), [235]);
    }
    $cmd("MAIL FROM:<$from>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $data = preg_replace('/^\./m', '..', $data);
    $cmd($data . "\r\n.", [250]);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
}

/**
 * Subscribe an email (double-subscribe safe). Consent text is recorded verbatim.
 * Returns 'subscribed' | 'already' | 'invalid'.
 */
function newsletter_subscribe(string $email, string $consentText, string $source = 'footer'): string
{
    $email = mb_strtolower(trim($email));
    if (!valid_email($email)) {
        return 'invalid';
    }
    $existing = db_one('SELECT * FROM newsletter_subscribers WHERE email = ?', [$email]);
    if ($existing && $existing['status'] === 'subscribed') {
        return 'already';
    }
    $token = random_token(32);
    if ($existing) {
        db_exec("UPDATE newsletter_subscribers SET status = 'subscribed', consent_text = ?, consent_ip = ?, source = ?, unsubscribe_token = ?, subscribed_at = NOW(), unsubscribed_at = NULL WHERE id = ?",
            [mb_substr($consentText, 0, 255), client_ip(), $source, $token, $existing['id']]);
    } else {
        db_insert('newsletter_subscribers', ['email' => $email, 'consent_text' => mb_substr($consentText, 0, 255), 'consent_ip' => client_ip(), 'source' => $source, 'unsubscribe_token' => $token]);
    }
    if (setting_bool('newsletter_welcome_email', true)) {
        send_template_email($email, 'Welcome to ' . setting('site_name', 'Beglet'), 'newsletter_welcome', ['unsubscribe' => url('newsletter/unsubscribe', ['token' => $token])]);
    }
    return 'subscribed';
}
