<?php
/** Escaping, CSRF, rate limiting, secrets and signed links. */

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function random_token(int $bytes = 20): string
{
    return bin2hex(random_bytes($bytes));
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || str_starts_with(APP_URL, 'https://');
}

function set_cookie(string $name, string $value, int $ttl, bool $httpOnly = true): void
{
    setcookie($name, $value, ['expires' => $ttl ? time() + $ttl : 0, 'path' => app_base_path() . '/', 'secure' => is_https(), 'httponly' => $httpOnly, 'samesite' => 'Lax']);
    $_COOKIE[$name] = $value;
}

function clear_cookie(string $name): void
{
    setcookie($name, '', ['expires' => time() - 3600, 'path' => app_base_path() . '/']);
    unset($_COOKIE[$name]);
}

// ---------------------------------------------------------------- CSRF (server-rendered forms)
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = random_token(16);
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------------- rate limiting (fixed window in MySQL)
function rate_limit(string $key, int $max, int $window): bool
{
    $key = substr($key, 0, 190);
    $now = time();
    $row = db_row('SELECT hits, window_start FROM rate_limits WHERE rl_key = ?', [$key]);
    if (!$row || $row['window_start'] + $window <= $now) {
        db_exec('REPLACE INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, ?)', [$key, $now]);
        return true;
    }
    if ((int) $row['hits'] >= $max) {
        return false;
    }
    db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?', [$key]);
    return true;
}

function rate_limit_clear(string $key): void
{
    db_exec('DELETE FROM rate_limits WHERE rl_key = ?', [$key]);
}

// ---------------------------------------------------------------- secrets
function app_key_bytes(): string
{
    return hash('sha256', APP_KEY, true);
}

/** AES-256-GCM; used for Wi-Fi passwords. */
function encrypt_secret(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $iv = random_bytes(12);
    $tag = '';
    $c = openssl_encrypt($plain, 'aes-256-gcm', app_key_bytes(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $c);
}

function decrypt_secret(?string $enc): string
{
    if (!$enc) {
        return '';
    }
    $raw = base64_decode($enc, true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', app_key_bytes(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}

/** HMAC signature for temporary links (file downloads). */
function sign(string $data): string
{
    return hash_hmac('sha256', $data, APP_KEY);
}

function password_ok(string $pw): ?string
{
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password must contain letters and numbers.';
    }
    return null;
}

function send_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(self), camera=(self)');
}
