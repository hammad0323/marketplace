<?php
/**
 * Minimal pure-PHP SMTP client (no external libraries) - supports plain, SSL (implicit,
 * typically port 465) and STARTTLS (typically port 587), with AUTH LOGIN. Used by the
 * Email Engine when a Super Admin configures SMTP in Platform > Email Settings; this is
 * what makes outbound email actually work on shared hosting, where PHP's mail() is
 * frequently disabled, unauthenticated, or silently dropped by the receiving server.
 */

function smtp_send(array $config, string $toEmail, string $toName, string $subject, string $bodyHtml, ?string &$error = null): bool
{
    $host = trim($config['host'] ?? '');
    $port = (int)($config['port'] ?? 587);
    $secure = $config['secure'] ?? 'tls'; // tls (STARTTLS) | ssl (implicit) | none
    $username = $config['username'] ?? '';
    $password = $config['password'] ?? '';
    $fromEmail = $config['from_email'] ?? ('no-reply@' . $host);
    $fromName = $config['from_name'] ?? $fromEmail;

    if ($host === '') {
        $error = 'SMTP host is not configured.';
        return false;
    }

    $remote = ($secure === 'ssl' ? 'ssl://' : '') . $host;
    $sock = @stream_socket_client("$remote:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
    if (!$sock) {
        $error = "Could not connect to SMTP server: $errstr ($errno)";
        return false;
    }
    stream_set_timeout($sock, 15);

    $read = function () use ($sock): string {
        $data = '';
        while (($line = fgets($sock, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // last line of a multi-line reply
        }
        return $data;
    };
    $write = function (string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };
    $expect = function (string $expectedCode) use ($read, &$error): bool {
        $response = $read();
        if (substr($response, 0, 3) !== $expectedCode) {
            $error = "SMTP error, expected $expectedCode, got: " . trim($response);
            return false;
        }
        return true;
    };

    if (!$expect('220')) { fclose($sock); return false; }

    $ehloHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $write("EHLO $ehloHost");
    if (!$expect('250')) { fclose($sock); return false; }

    if ($secure === 'tls') {
        $write('STARTTLS');
        if (!$expect('220')) { fclose($sock); return false; }
        if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $error = 'Failed to enable TLS encryption.';
            fclose($sock);
            return false;
        }
        $write("EHLO $ehloHost");
        if (!$expect('250')) { fclose($sock); return false; }
    }

    if ($username !== '') {
        $write('AUTH LOGIN');
        if (!$expect('334')) { fclose($sock); return false; }
        $write(base64_encode($username));
        if (!$expect('334')) { fclose($sock); return false; }
        $write(base64_encode($password));
        if (!$expect('235')) { fclose($sock); return false; }
    }

    $write("MAIL FROM:<$fromEmail>");
    if (!$expect('250')) { fclose($sock); return false; }
    $write("RCPT TO:<$toEmail>");
    if (!$expect('250')) { fclose($sock); return false; }
    $write('DATA');
    if (!$expect('354')) { fclose($sock); return false; }

    $boundary = md5(uniqid((string)mt_rand(), true));
    $headers = [
        'Date: ' . date('r'),
        'Message-ID: <' . $boundary . '@' . preg_replace('/[^a-z0-9.-]/i', '', $host) . '>',
        'From: ' . smtp_encode_header($fromName) . " <$fromEmail>",
        'To: ' . smtp_encode_header($toName) . " <$toEmail>",
        'Subject: ' . smtp_encode_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $data = implode("\r\n", $headers) . "\r\n\r\n" . smtp_dot_stuff($bodyHtml) . "\r\n.";
    $write($data);
    if (!$expect('250')) { fclose($sock); return false; }

    $write('QUIT');
    fclose($sock);
    return true;
}

function smtp_dot_stuff(string $body): string
{
    return preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\r\n", $body));
}

function smtp_encode_header(string $text): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $text)) {
        return $text; // pure ASCII, no encoding needed
    }
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}
