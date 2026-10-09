<?php
/** Symmetric encryption for stored secrets (payment gateway credentials). */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function app_key(): string
{
    $k = base64_decode(APP_KEY, true);
    if ($k === false || strlen($k) < 32) {
        // Fall back to a derived key so the site still runs, but log loudly.
        error_log('APP_KEY is missing or too short; set a 32-byte base64 key in config/config.php');
        $k = hash('sha256', APP_KEY . DB_NAME . DB_USER, true);
    }
    return substr($k, 0, 32);
}

function encrypt_secret(string $plain): string
{
    if ($plain === '') return '';
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function decrypt_secret(?string $stored): string
{
    if (!$stored || !str_starts_with($stored, 'v1:')) return '';
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) return '';
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}
