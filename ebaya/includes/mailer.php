<?php
/**
 * Minimal mailer: SMTP (STARTTLS/SSL, AUTH LOGIN) when configured, otherwise
 * PHP mail(). Disabled entirely until "Send emails" is switched on in
 * Admin → Settings → Email. Every attempt is logged in email_log.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function mail_wrap(string $subject, string $bodyHtml): string
{
    $brand = e(setting('site_name', 'Ebaya'));
    return '<!doctype html><html><body style="margin:0;background:#F8F5EF;font-family:Georgia,serif;color:#332820">'
        . '<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 12px">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#fff;border:1px solid #E7D8C4">'
        . '<tr><td style="background:#354638;color:#F8F5EF;text-align:center;padding:22px;font-size:26px;letter-spacing:4px">' . $brand . '</td></tr>'
        . '<tr><td style="padding:28px;font-family:Arial,sans-serif;font-size:14px;line-height:1.6">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:16px;text-align:center;font-size:12px;color:#8a7d70;font-family:Arial,sans-serif">' . e(setting('contact_email')) . ' · ' . e(setting('contact_phone')) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function send_mail(string $to, string $subject, string $bodyHtml): bool
{
    if (!v_email($to)) return false;
    $subject = str_replace(["\r", "\n"], '', $subject);
    if (!setting('mail_enabled')) {
        db_insert('INSERT INTO email_log (recipient, subject, status, error) VALUES (?, ?, \'skipped\', ?)', [$to, $subject, 'Email sending disabled']);
        return false;
    }
    $html = mail_wrap($subject, $bodyHtml);
    $fromEmail = setting('mail_from_email', 'no-reply@example.com');
    $fromName = str_replace(["\r", "\n", '"'], '', setting('mail_from_name', 'Ebaya'));
    $error = null;
    try {
        if (setting('smtp_host')) {
            smtp_send($to, $subject, $html, $fromEmail, $fromName);
        } else {
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: \"$fromName\" <$fromEmail>\r\n";
            if (!mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers)) $error = 'mail() returned false';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    db_insert('INSERT INTO email_log (recipient, subject, status, error) VALUES (?, ?, ?, ?)', [$to, $subject, $error ? 'failed' : 'sent', $error ? mb_substr($error, 0, 255) : null]);
    return $error === null;
}

function smtp_send(string $to, string $subject, string $html, string $fromEmail, string $fromName): void
{
    $host = setting('smtp_host');
    $port = (int)setting('smtp_port', 587);
    $secure = setting('smtp_secure', 'tls');
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]));
    if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read) {
        fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP error: ' . trim($r));
        return $r;
    };
    $read();
    $ehlo = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    $cmd("EHLO $ehlo", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('STARTTLS failed');
        $cmd("EHLO $ehlo", [250]);
    }
    if (setting('smtp_user')) {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(setting('smtp_user')), [334]);
        $cmd(base64_encode(decrypt_secret(setting('smtp_pass', ''))), [235]);
    }
    $cmd("MAIL FROM:<$fromEmail>", [250]);
    $cmd("RCPT TO:<$to>", [250, 251]);
    $cmd('DATA', [354]);
    $headers = 'From: "' . $fromName . '" <' . $fromEmail . ">\r\nTo: <$to>\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
        . "Date: " . date('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
    $cmd($headers . "\r\n" . chunk_split(base64_encode($html)) . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}
