<?php
/** URLs, input, formatting and small utilities shared by every part of the platform. */

function app_base_path(): string
{
    static $p = null;
    return $p ??= rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
}

function url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/** Same-origin relative URL (used in pages so they work behind any host name). */
function path(string $path = ''): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

function asset(string $file): string
{
    $full = PH_ROOT . '/assets/' . $file;
    return path('assets/' . $file) . (is_file($full) ? '?v=' . filemtime($full) : '');
}

function shop_url(array $shop): string
{
    return url('shop/' . $shop['shop_code']);
}

function redirect(string $to): never
{
    header('Location: ' . (preg_match('#^https?://#', $to) ? $to : path($to)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Request body: JSON (apps) or form fields (web). */
function request_data(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $data = $_POST;
    $type = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($type, 'application/json')) {
        $json = json_decode((string) file_get_contents('php://input'), true);
        $data = is_array($json) ? $json : [];
    }
    return $data;
}

function in(string $key, $default = null)
{
    $d = request_data();
    $v = $d[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function in_str(string $key, int $max = 255, string $default = ''): string
{
    $v = in($key, $default);
    return is_scalar($v) ? mb_substr(trim((string) $v), 0, $max) : $default;
}

function in_int(string $key, int $default = 0): int
{
    $v = in($key, $default);
    return is_numeric($v) ? (int) $v : $default;
}

function in_float(string $key, float $default = 0.0): float
{
    $v = in($key, $default);
    return is_numeric($v) ? (float) $v : $default;
}

function in_bool(string $key): bool
{
    $v = in($key, false);
    return in_array($v, [true, 1, '1', 'true', 'on', 'yes'], true);
}

function nullable(?string $v): ?string
{
    $v = $v === null ? null : trim($v);
    return $v === '' ? null : $v;
}

function money($amount): string
{
    return 'Rs. ' . number_format((float) $amount, 0);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function format_date(?string $d, string $fmt = 'j M Y'): string
{
    return $d ? date($fmt, strtotime($d)) : '—';
}

function format_datetime(?string $d): string
{
    return $d ? date('j M Y, g:i A', strtotime($d)) : '—';
}

function time_ago(?string $d): string
{
    if (!$d) {
        return '—';
    }
    $s = time() - strtotime($d);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    if ($s < 604800) return floor($s / 86400) . ' d ago';
    return format_date($d);
}

function valid_phone(string $phone): bool
{
    return strlen(preg_replace('/\D/', '', $phone)) >= 10 && strlen(preg_replace('/\D/', '', $phone)) <= 15;
}

function clean_phone(string $phone): string
{
    return mb_substr(preg_replace('/[^0-9+ \-]/', '', $phone), 0, 30);
}

function valid_color(?string $c, string $fallback): string
{
    return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : $fallback;
}

function str_limit(?string $s, int $n): string
{
    return mb_strimwidth((string) $s, 0, $n, '…');
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function paginate(int $total, int $perPage = 25): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    return ['page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function pagination_html(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $q = $_GET;
    $html = '<nav><ul class="pagination pagination-sm">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        if ($pg['pages'] > 12 && abs($i - $pg['page']) > 3 && $i !== 1 && $i !== $pg['pages']) {
            if ($i === 2 || $i === $pg['pages'] - 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            continue;
        }
        $q['page'] = $i;
        $html .= '<li class="page-item' . ($i === $pg['page'] ? ' active' : '') . '"><a class="page-link" href="?' . e(http_build_query($q)) . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}

function setting(string $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM platform_settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    if (func_num_args() === 3) {
        $cache[$key] = func_get_arg(2);
    }
    return $cache[$key] ?? $default;
}

function setting_save(string $key, string $value): void
{
    db_exec('INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
    setting($key, '', $value);
}

function platform_name(): string
{
    return setting('platform_name', 'PrintHub');
}

function activity(string $actorType, ?int $actorId, string $action, ?int $shopId = null, ?string $entity = null, ?int $entityId = null, array $details = []): void
{
    db_insert('activity_logs', ['actor_type' => $actorType, 'actor_id' => $actorId, 'shop_id' => $shopId, 'action' => $action, 'entity_type' => $entity,
        'entity_id' => $entityId, 'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null, 'ip_address' => client_ip()]);
}

/** Minimal mail() wrapper; returns false when mail is not configured on the host. */
function send_mail(string $to, string $subject, string $text): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
        return false;
    }
    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . ">\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
}
