<?php
/**
 * Minimal, dependency-free SMTP mailer. Reads credentials from the
 * `settings` table (Admin -> Settings -> Email / SMTP). Falls back to
 * PHP's mail() when no SMTP host is configured, and always logs
 * failures to logs/mail.log so a broken mail config never breaks
 * checkout or registration.
 */

function mail_log($message) {
    $dir = ROOT_PATH . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/mail.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

function encode_header_value($value) {
    if (preg_match('/[^\x20-\x7E]/', $value)) {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
    return $value;
}

function send_email($to, $subject, $htmlBody, $toName = '') {
    $fromEmail = get_setting('smtp_from_email') ?: get_setting('store_email');
    $fromName = get_setting('smtp_from_name') ?: get_setting('store_name');
    if (!$fromEmail || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        mail_log("Skipped send to '$to': missing from-address or invalid recipient.");
        return false;
    }

    $host = get_setting('smtp_host');
    try {
        if ($host) {
            return send_via_smtp($to, $toName, $subject, $htmlBody, $fromEmail, $fromName);
        }
        return send_via_php_mail($to, $subject, $htmlBody, $fromEmail, $fromName);
    } catch (Throwable $e) {
        mail_log('Exception: ' . $e->getMessage());
        return false;
    }
}

function build_mime_message($fromEmail, $fromName, $to, $toName, $subject) {
    $boundary = null;
    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . encode_header_value($fromName) . ' <' . $fromEmail . '>';
    $headers[] = 'To: ' . ($toName ? encode_header_value($toName) . ' <' . $to . '>' : $to);
    $headers[] = 'Subject: ' . encode_header_value($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';
    $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . preg_replace('~^https?://~', '', BASE_URL) . '>';
    return implode("\r\n", $headers);
}

function send_via_php_mail($to, $subject, $htmlBody, $fromEmail, $fromName) {
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . encode_header_value($fromName) . ' <' . $fromEmail . ">\r\n";
    $ok = @mail($to, encode_header_value($subject), $htmlBody, $headers);
    if (!$ok) mail_log("PHP mail() failed for $to");
    return $ok;
}

function smtp_read($socket) {
    $data = '';
    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') break;
        if (strlen($line) < 4) break;
    }
    return $data;
}

function smtp_write($socket, $cmd) {
    fwrite($socket, $cmd . "\r\n");
}

function send_via_smtp($to, $toName, $subject, $htmlBody, $fromEmail, $fromName) {
    $host = get_setting('smtp_host');
    $port = (int)get_setting('smtp_port', '587');
    $username = get_setting('smtp_username');
    $password = get_setting('smtp_password');
    $encryption = get_setting('smtp_encryption', 'tls');

    $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15);
    if (!$socket) {
        mail_log("SMTP connect failed to $host:$port - $errstr");
        return false;
    }
    stream_set_timeout($socket, 15);
    $localHost = preg_replace('~^https?://~', '', BASE_URL);
    $localHost = explode('/', $localHost)[0] ?: 'localhost';

    $resp = smtp_read($socket);
    if (substr($resp, 0, 3) !== '220') { mail_log("No SMTP greeting: $resp"); fclose($socket); return false; }

    smtp_write($socket, 'EHLO ' . $localHost);
    smtp_read($socket);

    if ($encryption === 'tls') {
        smtp_write($socket, 'STARTTLS');
        $resp = smtp_read($socket);
        if (substr($resp, 0, 3) !== '220') { mail_log("STARTTLS rejected: $resp"); fclose($socket); return false; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            mail_log('TLS negotiation failed.');
            fclose($socket);
            return false;
        }
        smtp_write($socket, 'EHLO ' . $localHost);
        smtp_read($socket);
    }

    if ($username) {
        smtp_write($socket, 'AUTH LOGIN');
        smtp_read($socket);
        smtp_write($socket, base64_encode($username));
        smtp_read($socket);
        smtp_write($socket, base64_encode($password));
        $resp = smtp_read($socket);
        if (substr($resp, 0, 3) !== '235') { mail_log("SMTP auth failed: $resp"); fclose($socket); smtp_write($socket, 'QUIT'); return false; }
    }

    smtp_write($socket, 'MAIL FROM:<' . $fromEmail . '>');
    $resp = smtp_read($socket);
    if (substr($resp, 0, 2) !== '25') { mail_log("MAIL FROM rejected: $resp"); fclose($socket); return false; }

    smtp_write($socket, 'RCPT TO:<' . $to . '>');
    $resp = smtp_read($socket);
    if (substr($resp, 0, 2) !== '25') { mail_log("RCPT TO rejected for $to: $resp"); fclose($socket); return false; }

    smtp_write($socket, 'DATA');
    $resp = smtp_read($socket);
    if (substr($resp, 0, 3) !== '354') { mail_log("DATA rejected: $resp"); fclose($socket); return false; }

    $headers = build_mime_message($fromEmail, $fromName, $to, $toName, $subject);
    $body = str_replace("\n.", "\n..", str_replace("\r\n", "\n", $htmlBody));
    $body = str_replace("\n", "\r\n", $body);
    fwrite($socket, $headers . "\r\n\r\n" . $body . "\r\n.\r\n");
    $resp = smtp_read($socket);
    if (substr($resp, 0, 2) !== '25') { mail_log("Message rejected: $resp"); fclose($socket); return false; }

    smtp_write($socket, 'QUIT');
    fclose($socket);
    return true;
}
