<?php
/**
 * Security Engine - CSRF, XSS, input sanitization, rate limiting.
 */
require_once __DIR__ . '/config.php';

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return !empty($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrf_require(): void
{
    if (!csrf_verify()) {
        http_response_code(403);
        if (is_ajax_request()) {
            json_response(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
        }
        include __DIR__ . '/../403.php';
        exit;
    }
}

function is_ajax_request(): bool
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function clean_str($value): string
{
    if ($value === null) {
        return '';
    }
    return trim(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}

function clean_input($value)
{
    if (is_array($value)) {
        return array_map('clean_input', $value);
    }
    return trim(strip_tags((string)$value));
}

function out(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? clean_input($_POST[$key]) : $default;
}

function get_param(string $key, $default = '')
{
    return isset($_GET[$key]) ? clean_input($_GET[$key]) : $default;
}

function post_raw(string $key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int)$_POST[$key] : $default;
}

function post_float(string $key, float $default = 0.0): float
{
    return isset($_POST[$key]) ? (float)$_POST[$key] : $default;
}

function get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}

/**
 * Simple file-based rate limiting per identifier+action.
 */
function rate_limit(string $identifier, string $action, int $maxAttempts = 5, int $windowSeconds = 300): bool
{
    $dir = sys_get_temp_dir() . '/qms_rate';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $key = md5($identifier . '|' . $action);
    $file = $dir . '/' . $key . '.json';
    $now = time();
    $attempts = [];
    if (is_file($file)) {
        $attempts = json_decode((string)file_get_contents($file), true) ?: [];
    }
    $attempts = array_filter($attempts, fn($t) => $t > $now - $windowSeconds);
    if (count($attempts) >= $maxAttempts) {
        return false;
    }
    $attempts[] = $now;
    @file_put_contents($file, json_encode(array_values($attempts)));
    return true;
}

function validate_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validate_file_upload(array $file, array $allowedExt = ALLOWED_FILE_EXT, int $maxSize = MAX_FILE_SIZE): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['valid' => false, 'error' => 'No file uploaded'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload error code ' . $file['error']];
    }
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File exceeds maximum size'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['valid' => false, 'error' => 'File type not allowed'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
        'doc' => ['application/msword'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'], 'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
    ];
    if (isset($allowedMimes[$ext]) && !in_array($mime, $allowedMimes[$ext], true)) {
        return ['valid' => false, 'error' => 'File content does not match its extension'];
    }
    return ['valid' => true, 'ext' => $ext, 'mime' => $mime];
}

function save_uploaded_file(array $file, string $subfolder, array $allowedExt = ALLOWED_FILE_EXT): ?array
{
    $check = validate_file_upload($file, $allowedExt);
    if (!$check['valid']) {
        return null;
    }
    $dir = UPLOAD_PATH . '/' . trim($subfolder, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $check['ext'];
    $dest = $dir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return null;
    }
    return [
        'filename' => $filename,
        'original_name' => $file['name'],
        'path' => trim($subfolder, '/') . '/' . $filename,
        'url' => UPLOAD_URL . '/' . trim($subfolder, '/') . '/' . $filename,
        'size' => $file['size'],
        'mime' => $check['mime'],
    ];
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

function generate_random_password(int $length = 12): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}
