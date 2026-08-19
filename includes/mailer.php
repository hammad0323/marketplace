<?php
/**
 * Outbound email — a small hand-rolled SMTP client (no Composer/PHPMailer
 * dependency, consistent with the rest of this project) with a fallback to
 * PHP's built-in mail() when SMTP isn't configured. Included once via
 * config/config.php.
 *
 * Configure via Admin → Site Settings → Email (SMTP), stored in
 * site_settings. Sending is synchronous and best-effort: a slow or
 * misconfigured mail server should never break the page that triggered the
 * email, so every failure is caught and logged rather than thrown.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

function email_enabled()
{
    return get_setting('email_notifications_enabled', '1') === '1';
}

/**
 * Sends an HTML email. Returns true on success, false on failure (logged,
 * never thrown). Uses SMTP if smtp_host is configured in site_settings,
 * otherwise falls back to PHP's mail().
 */
function send_email($toEmail, $toName, $subject, $htmlBody, $force = false)
{
    if ((!$force && !email_enabled()) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $fromEmail = get_setting('smtp_from_email') ?: get_setting('contact_email', 'no-reply@' . preg_replace('/^www\./', '', parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost'));
    $fromName = get_setting('smtp_from_name') ?: get_setting('site_name', SITE_NAME);
    $host = get_setting('smtp_host');

    try {
        if ($host !== '') {
            return smtp_send($host, $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody);
        }
        return mail_fallback_send($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody);
    } catch (Throwable $e) {
        error_log('send_email failed: ' . $e->getMessage());
        return false;
    }
}

function mail_fallback_send($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody)
{
    $headers = "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . 'From: ' . mime_header_encode($fromName) . ' <' . $fromEmail . ">\r\n"
        . 'Reply-To: ' . $fromEmail . "\r\n";
    return @mail($toEmail, mime_header_encode($subject), $htmlBody, $headers);
}

function mime_header_encode($text)
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/**
 * Minimal RFC 5321-ish SMTP client over a raw socket. Supports SMTPS
 * (implicit TLS, typically port 465) and STARTTLS (typically port 587),
 * plus AUTH LOGIN. Good enough for sending transactional email through any
 * standard provider (Gmail/Workspace, SES, Mailgun, Postmark, a shared
 * host's own mail server, etc).
 */
function smtp_send($host, $fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody)
{
    $port = (int) (get_setting('smtp_port') ?: 587);
    $encryption = get_setting('smtp_encryption', 'tls'); // 'ssl', 'tls', or 'none'
    $username = get_setting('smtp_username');
    $password = get_setting('smtp_password');
    $timeout = 8;

    $transport = $encryption === 'ssl' ? 'ssl://' : '';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, $timeout);
    if (!$socket) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($socket, $timeout);

    $expect = function ($expectedCode) use ($socket) {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SMTP unexpected response (wanted $expectedCode): " . trim($response));
        }
        return $response;
    };
    $send = function ($command) use ($socket) {
        fwrite($socket, $command . "\r\n");
    };

    try {
        $expect(220);
        $localHost = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
        $send('EHLO ' . $localHost);
        $expect(250);

        if ($encryption === 'tls') {
            $send('STARTTLS');
            $expect(220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed.');
            }
            $send('EHLO ' . $localHost);
            $expect(250);
        }

        if ($username !== '') {
            $send('AUTH LOGIN');
            $expect(334);
            $send(base64_encode($username));
            $expect(334);
            $send(base64_encode($password));
            $expect(235);
        }

        $send('MAIL FROM:<' . $fromEmail . '>');
        $expect(250);
        $send('RCPT TO:<' . $toEmail . '>');
        $expect(250);
        $send('DATA');
        $expect(354);

        $headers = [];
        $headers[] = 'From: ' . mime_header_encode($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'To: ' . mime_header_encode($toName ?: $toEmail) . ' <' . $toEmail . '>';
        $headers[] = 'Subject: ' . mime_header_encode($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $localHost . '>';

        // Dot-stuff any line that starts with a lone "." per RFC 5321.
        $body = preg_replace('/^\./m', '..', $htmlBody);
        $send(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.");
        $expect(250);

        $send('QUIT');
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP send failed: ' . $e->getMessage());
        fclose($socket);
        return false;
    }
}

/**
 * Wraps content in the shared branded email shell (header, footer, CTA
 * button). $ctaUrl should be an absolute URL (use APP_URL . '/path').
 */
function email_template($title, $bodyHtml, $ctaText = null, $ctaUrl = null)
{
    $siteName = e(get_setting('site_name', SITE_NAME));
    $ctaHtml = '';
    if ($ctaText && $ctaUrl) {
        $ctaHtml = '<tr><td style="padding:8px 0 4px;">
            <a href="' . e($ctaUrl) . '" style="display:inline-block;padding:13px 28px;border-radius:999px;background:#0C6B5D;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;">' . e($ctaText) . '</a>
        </td></tr>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#F3FCFB;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F3FCFB;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;">
<tr><td style="background:linear-gradient(135deg,#0C6B5D,#22C3AB);padding:28px 32px;">
    <span style="color:#ffffff;font-size:20px;font-weight:800;">' . $siteName . '</span>
</td></tr>
<tr><td style="padding:32px;">
    <h2 style="margin:0 0 16px;color:#1F2937;font-size:20px;">' . e($title) . '</h2>
    <div style="color:#4B5563;font-size:14.5px;line-height:1.7;">' . $bodyHtml . '</div>
    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:24px;">' . $ctaHtml . '</table>
</td></tr>
<tr><td style="padding:20px 32px;background:#F3FCFB;color:#9CA3AF;font-size:12px;">
    This is an automated message from ' . $siteName . '. Please do not reply directly to this email.
</td></tr>
</table>
</td></tr>
</table>
</body></html>';
}
