<?php
/**
 * Core procedural helpers: request parsing, MySQLi wrappers, ids, logging.
 */

// ---------------------------------------------------------------------------
// Request
// ---------------------------------------------------------------------------
function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function require_method(string ...$methods): void
{
    if (!in_array(request_method(), $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        fail('Method not allowed', 405, 'METHOD_NOT_ALLOWED');
    }
}

/** JSON body merged over form fields and query string (body wins). */
function input(): array
{
    static $data = null;
    if ($data !== null) return $data;
    $data = $_GET;
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw !== '' && $raw !== false) {
            $json = json_decode($raw, true);
            if (!is_array($json)) fail('Request body must be valid JSON', 400, 'VALIDATION');
            $data = array_merge($data, $json);
        }
    } else {
        $data = array_merge($data, $_POST);
    }
    return $data;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function base_url(): string
{
    if (APP_URL !== '') return rtrim(APP_URL, '/');
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $pos    = strpos($script, '/api/');
    $prefix = $pos === false ? '' : substr($script, 0, $pos);
    return ($https ? 'https' : 'http') . '://' . $host . $prefix;
}

// ---------------------------------------------------------------------------
// CORS + security headers
// ---------------------------------------------------------------------------
function send_cors_headers(): void
{
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_map('trim', explode(',', CORS_ORIGINS));
    if (in_array('*', $allowed, true)) {
        header('Access-Control-Allow-Origin: *');
    } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    if (APP_ENV === 'production') header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    if (request_method() === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ---------------------------------------------------------------------------
// MySQLi wrappers (prepared statements only)
// ---------------------------------------------------------------------------
function db_run(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params) {
        $types = '';
        $vals  = [];
        foreach ($params as $p) {
            if (is_bool($p)) { $types .= 'i'; $vals[] = $p ? 1 : 0; }
            elseif (is_int($p)) { $types .= 'i'; $vals[] = $p; }
            elseif (is_float($p)) { $types .= 'd'; $vals[] = $p; }
            elseif (is_array($p)) { $types .= 's'; $vals[] = json_encode($p, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); }
            else { $types .= 's'; $vals[] = $p === null ? null : (string)$p; }
        }
        $stmt->bind_param($types, ...$vals);
    }
    $stmt->execute();
    return $stmt;
}

function db_all(string $sql, array $params = []): array
{
    $res = db_run($sql, $params)->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function db_one(string $sql, array $params = []): ?array
{
    $res = db_run($sql, $params)->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

function db_val(string $sql, array $params = [])
{
    $row = db_one($sql, $params);
    return $row ? reset($row) : null;
}

function db_exec(string $sql, array $params = []): int
{
    return db_run($sql, $params)->affected_rows;
}

function db_insert(string $sql, array $params = []): int
{
    db_run($sql, $params);
    return (int)db()->insert_id;
}

function db_in_transaction(): bool
{
    return !empty($GLOBALS['__in_txn']);
}

function db_begin(): void
{
    db()->begin_transaction();
    $GLOBALS['__in_txn'] = true;
}

function db_commit(): void
{
    db()->commit();
    $GLOBALS['__in_txn'] = false;
}

function db_rollback(): void
{
    if (db_in_transaction()) db()->rollback();
    $GLOBALS['__in_txn'] = false;
}

/** Build "?, ?, ?" for IN() lists. */
function placeholders(array $items): string
{
    return implode(', ', array_fill(0, max(1, count($items)), '?'));
}

// ---------------------------------------------------------------------------
// Data helpers
// ---------------------------------------------------------------------------
function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

function uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function sha256(string $v): string
{
    return hash('sha256', $v);
}

function jdecode($v, $default = null)
{
    if ($v === null || $v === '') return $default;
    if (is_array($v)) return $v;
    $d = json_decode((string)$v, true);
    return $d === null && json_last_error() !== JSON_ERROR_NONE ? $default : $d;
}

function jencode($v): string
{
    return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
}

/** Decode listed JSON columns and cast DECIMAL strings to float. */
function shape_row(?array $row, array $json = [], array $floats = []): ?array
{
    if ($row === null) return null;
    foreach ($json as $c) if (array_key_exists($c, $row)) $row[$c] = jdecode($row[$c]);
    foreach ($floats as $c) if (isset($row[$c])) $row[$c] = (float)$row[$c];
    return $row;
}

function shape_rows(array $rows, array $json = [], array $floats = []): array
{
    return array_map(fn($r) => shape_row($r, $json, $floats), $rows);
}

/** page/per_page from query → [page, perPage, offset]. */
function pagination(): array
{
    $in = input();
    $page = max(1, (int)($in['page'] ?? 1));
    $per  = min(PER_PAGE_MAX, max(1, (int)($in['per_page'] ?? PER_PAGE_DEFAULT)));
    return [$page, $per, ($page - 1) * $per];
}

function app_setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value, value_type FROM app_settings') as $r) {
            $v = $r['setting_value'];
            $cache[$r['setting_key']] = match ($r['value_type']) {
                'int'  => (int)$v,
                'bool' => in_array(strtolower((string)$v), ['1', 'true', 'yes'], true),
                'json' => jdecode($v, []),
                default => $v,
            };
        }
    }
    return $cache[$key] ?? $default;
}

// ---------------------------------------------------------------------------
// Audit / notifications / logging
// ---------------------------------------------------------------------------
function log_activity(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null, ?array $meta = null): void
{
    db_run('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, meta_json, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$userId, $action, $entityType, $entityId, $description ? mb_substr($description, 0, 500) : null, $meta ? jencode($meta) : null, client_ip()]);
}

function notify(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): void
{
    db_run('INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)',
        [$userId, mb_substr($title, 0, 150), mb_substr($message, 0, 1000), $type, $link]);
}

function app_log(string $message, string $file = 'app.log'): void
{
    @file_put_contents(rtrim(LOG_PATH, '/') . '/' . $file, '[' . now_utc() . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function send_mail(string $to, string $subject, string $body): bool
{
    if (MAIL_ENABLED === '1') {
        $headers = 'From: ' . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8";
        return @mail($to, $subject, $body, $headers);
    }
    app_log("TO: {$to}\nSUBJECT: {$subject}\n{$body}\n----", 'mail.log');
    return true;
}

// ---------------------------------------------------------------------------
// Signed media URLs
// ---------------------------------------------------------------------------
function media_signature(int $mediaId, int $exp, string $variant): string
{
    return substr(hash_hmac('sha256', "{$mediaId}|{$exp}|{$variant}", APP_SECRET), 0, 32);
}

function media_url(?int $mediaId, string $variant = 'file'): ?string
{
    if (!$mediaId) return null;
    $ttl = max(1, (int)MEDIA_URL_TTL_DAYS) * 86400;
    $exp = (int)(ceil((time() + $ttl) / 86400) * 86400);   // day-aligned → cache friendly URLs
    $sig = media_signature($mediaId, $exp, $variant);
    return base_url() . "/api/media/file.php?id={$mediaId}&v={$variant}&exp={$exp}&sig={$sig}";
}

/** Add url / thumbnail_url to a media row and hide server paths. */
function present_media(array $m): array
{
    $m['url'] = media_url((int)$m['media_id']);
    $m['thumbnail_url'] = !empty($m['thumbnail_path']) ? media_url((int)$m['media_id'], 'thumb') : ($m['file_type'] === 'image' ? $m['url'] : null);
    unset($m['file_path'], $m['thumbnail_path'], $m['storage_driver']);
    return $m;
}
