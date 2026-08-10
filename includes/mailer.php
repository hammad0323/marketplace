<?php
/**
 * Minimal template-driven mailer. No external libraries — sends over raw
 * SMTP via fsockopen when config/settings.smtp_host is configured, and
 * always logs to email_log so the admin can see delivery history even
 * when no SMTP server is configured yet (the common case out of the box).
 */
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

/**
 * @param array<string,string> $vars Placeholders like {name} to replace in subject/body.
 */
function send_email($conn, $toEmail, $toName, $templateKey, array $vars = [])
{
    $template = db_select_one($conn, 'SELECT * FROM email_templates WHERE template_key = ? AND is_active = 1', [$templateKey]);
    if (!$template) {
        return false;
    }

    $vars = array_merge(['site_name' => get_setting($conn, 'site_name', APP_NAME)], $vars);
    $subject = strtr($template['subject'], wrap_placeholders($vars));
    $body = strtr($template['body_html'], wrap_placeholders($vars));

    $smtpHost = get_setting($conn, 'smtp_host', '');
    $status = 'logged_only';
    $error = null;

    if ($smtpHost !== '') {
        $result = smtp_send_mail(
            $smtpHost,
            (int) get_setting($conn, 'smtp_port', 587),
            get_setting($conn, 'smtp_username', ''),
            get_setting($conn, 'smtp_password', ''),
            get_setting($conn, 'smtp_encryption', 'tls'),
            get_setting($conn, 'smtp_from_email', 'no-reply@' . parse_url(APP_URL, PHP_URL_HOST)),
            get_setting($conn, 'smtp_from_name', APP_NAME),
            $toEmail,
            $toName,
            $subject,
            $body
        );
        $status = $result['ok'] ? 'sent' : 'failed';
        $error = $result['ok'] ? null : $result['error'];
    }

    db_execute(
        $conn,
        'INSERT INTO email_log (template_key, to_email, subject, body_html, status, error) VALUES (?, ?, ?, ?, ?, ?)',
        [$templateKey, $toEmail, $subject, $body, $status, $error]
    );

    return $status === 'sent' || $status === 'logged_only';
}

function wrap_placeholders(array $vars)
{
    $out = [];
    foreach ($vars as $key => $value) {
        $out['{' . $key . '}'] = (string) $value;
    }
    return $out;
}

/**
 * Minimal RFC 5321 SMTP client over fsockopen. Supports none/tls(STARTTLS)/ssl(implicit).
 * @return array{ok:bool, error?:string}
 */
function smtp_send_mail($host, $port, $username, $password, $encryption, $fromEmail, $fromName, $toEmail, $toName, $subject, $bodyHtml)
{
    $transport = $encryption === 'ssl' ? 'ssl://' : '';
    $fp = @fsockopen($transport . $host, $port, $errno, $errstr, 10);
    if (!$fp) {
        return ['ok' => false, 'error' => "Could not connect to $host:$port ($errstr)"];
    }
    stream_set_timeout($fp, 10);

    $expect = function ($fp, $code) {
        $line = '';
        do {
            $line = fgets($fp, 515);
            if ($line === false) {
                return false;
            }
        } while (isset($line[3]) && $line[3] === '-');
        return substr($line, 0, 3) === (string) $code;
    };
    $send = function ($fp, $cmd) { fwrite($fp, $cmd . "\r\n"); };

    if (!$expect($fp, 220)) {
        fclose($fp);
        return ['ok' => false, 'error' => 'No greeting from SMTP server'];
    }

    $localHost = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
    $send($fp, 'EHLO ' . $localHost);
    if (!$expect($fp, 250)) {
        fclose($fp);
        return ['ok' => false, 'error' => 'EHLO failed'];
    }

    if ($encryption === 'tls') {
        $send($fp, 'STARTTLS');
        if (!$expect($fp, 220) || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'STARTTLS failed'];
        }
        $send($fp, 'EHLO ' . $localHost);
        $expect($fp, 250);
    }

    if ($username !== '') {
        $send($fp, 'AUTH LOGIN');
        $expect($fp, 334);
        $send($fp, base64_encode($username));
        $expect($fp, 334);
        $send($fp, base64_encode($password));
        if (!$expect($fp, 235)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'SMTP authentication failed'];
        }
    }

    $send($fp, 'MAIL FROM:<' . $fromEmail . '>');
    if (!$expect($fp, 250)) {
        fclose($fp);
        return ['ok' => false, 'error' => 'MAIL FROM rejected'];
    }
    $send($fp, 'RCPT TO:<' . $toEmail . '>');
    if (!$expect($fp, 250)) {
        fclose($fp);
        return ['ok' => false, 'error' => 'RCPT TO rejected'];
    }
    $send($fp, 'DATA');
    if (!$expect($fp, 354)) {
        fclose($fp);
        return ['ok' => false, 'error' => 'DATA rejected'];
    }

    $headers = "From: {$fromName} <{$fromEmail}>\r\n"
        . "To: {$toName} <{$toEmail}>\r\n"
        . "Subject: {$subject}\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n";
    $escapedBody = preg_replace('/^\./m', '..', $bodyHtml);
    $send($fp, $headers . "\r\n" . $escapedBody . "\r\n.");
    if (!$expect($fp, 250)) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Message rejected'];
    }

    $send($fp, 'QUIT');
    fclose($fp);
    return ['ok' => true];
}
