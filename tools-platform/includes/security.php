<?php
/**
 * security.php — CSRF, output escaping, rate limiting, upload validation.
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

function tp_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function tp_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(tp_csrf_token(), ENT_QUOTES) . '">';
}

function tp_csrf_verify(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function tp_require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!tp_csrf_verify($token)) {
        http_response_code(419);
        exit('Your session expired or the form was tampered with. Please refresh and try again.');
    }
}

/** Escape for HTML output. Short alias used across templates. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Strict numeric sanitizer: returns null (not 0) when input isn't a valid number. */
function tp_sanitize_number($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_numeric($value)) {
        return null;
    }
    return (float) $value;
}

function tp_sanitize_text(?string $value, int $maxLength = 5000): string
{
    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLength);
    } else {
        $value = substr($value, 0, $maxLength);
    }
    return $value;
}

/**
 * Very small fixed-window rate limiter backed by the session — enough to
 * slow down brute-force login attempts without needing a cache server.
 */
function tp_rate_limit(string $key, int $maxAttempts, int $windowSeconds): bool
{
    $now = time();
    $bucket = $_SESSION['rate_limits'][$key] ?? ['count' => 0, 'reset_at' => $now + $windowSeconds];

    if ($now > $bucket['reset_at']) {
        $bucket = ['count' => 0, 'reset_at' => $now + $windowSeconds];
    }

    $bucket['count']++;
    $_SESSION['rate_limits'][$key] = $bucket;

    return $bucket['count'] <= $maxAttempts;
}

/**
 * Validate an uploaded image before it ever touches disk: real MIME
 * sniffed via getimagesize() (not the client-supplied Content-Type),
 * extension allow-list, and a size cap.
 */
function tp_validate_image_upload(array $file, int $maxBytes = 5242880): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File is too large (max ' . round($maxBytes / 1048576, 1) . 'MB).'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowed[$info['mime']])) {
        return ['ok' => false, 'error' => 'File is not a valid image.'];
    }

    return ['ok' => true, 'error' => null, 'extension' => $allowed[$info['mime']], 'mime' => $info['mime']];
}

/** Build a safe, collision-resistant filename for an upload. */
function tp_safe_upload_name(string $originalName, string $extension): string
{
    $base = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(pathinfo($originalName, PATHINFO_FILENAME)));
    $base = trim($base, '-') ?: 'file';
    return substr($base, 0, 60) . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
}
