<?php
/**
 * Session hardening, CSRF protection, login rate limiting, security headers.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

function fs_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('FSSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() ?: '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Rotate session id periodically to limit fixation windows.
    if (empty($_SESSION['_created'])) {
        $_SESSION['_created'] = time();
    } elseif (time() - $_SESSION['_created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }
}

function fs_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

/* ------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Abort the request if the CSRF token is missing/invalid. */
function csrf_check(): void
{
    if (!csrf_valid()) {
        if (is_ajax() || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            json_error('Your session expired. Please refresh the page and try again.', 419);
        }
        http_response_code(419);
        flash('danger', 'Your session expired. Please try again.');
        $back = $_SERVER['HTTP_REFERER'] ?? url('');
        header('Location: ' . $back);
        exit;
    }
}

/* ------------------------------------------------------------------
 * Login rate limiting (DB backed, per IP and per identifier)
 * ------------------------------------------------------------------ */
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;

function login_throttled(string $scope, string $identifier): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $byIp = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND ip_address = ? AND attempted_at > ?', [$scope, client_ip(), $since]);
    $byId = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE scope = ? AND identifier = ? AND attempted_at > ?', [$scope, mb_strtolower($identifier), $since]);
    return $byIp >= LOGIN_MAX_ATTEMPTS * 3 || $byId >= LOGIN_MAX_ATTEMPTS;
}

function login_failed(string $scope, string $identifier): void
{
    db_exec('INSERT INTO login_attempts (scope, identifier, ip_address) VALUES (?, ?, ?)', [$scope, mb_strtolower($identifier), client_ip()]);
    // Housekeeping
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    }
}

function login_clear(string $scope, string $identifier): void
{
    db_exec('DELETE FROM login_attempts WHERE scope = ? AND identifier = ?', [$scope, mb_strtolower($identifier)]);
}

/** Simple per-session throttle for public forms (contact, reviews, newsletter). */
function form_throttle(string $key, int $seconds = 20): bool
{
    $now = time();
    $last = $_SESSION['_throttle'][$key] ?? 0;
    if ($now - $last < $seconds) {
        return false;
    }
    $_SESSION['_throttle'][$key] = $now;
    return true;
}
