<?php
/**
 * General helper functions (output escaping, URLs, settings, formatting).
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

/** HTML-escape for output (XSS prevention). */
function e($s): string
{
    return htmlspecialchars((string) ($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path of the install, e.g. "" or "/fahim-store". */
function base_path(): string
{
    static $bp = null;
    if ($bp !== null) {
        return $bp;
    }
    if (APP_URL !== '') {
        $bp = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
        return $bp;
    }
    // Auto detect: document root relative path of the project folder.
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $root = realpath(FS_ROOT) ?: FS_ROOT;
    if ($docRoot && strpos($root, $docRoot) === 0) {
        $bp = rtrim(str_replace('\\', '/', substr($root, strlen($docRoot))), '/');
    } else {
        $bp = '';
    }
    return $bp;
}

/** Absolute site origin, e.g. https://example.com */
function site_origin(): string
{
    if (APP_URL !== '') {
        $p = parse_url(APP_URL);
        return $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^a-zA-Z0-9\.\-:\[\]]/', '', $host);
    return ($https ? 'https' : 'http') . '://' . $host;
}

/** Site-relative URL: url('product/abc/') => /product/abc/ */
function url(string $path = ''): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    return base_path() . '/' . ltrim($path, '/');
}

/** Absolute URL (for canonical, sitemap, OG). */
function abs_url(string $path = ''): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    return site_origin() . url($path);
}

function asset(string $path): string
{
    $file = FS_ROOT . '/assets/' . $path;
    if (USE_MIN_ASSETS && preg_match('/\.(css|js)$/', $path)) {
        $min = preg_replace('/\.(css|js)$/', '.min.$1', $path);
        if (is_file(FS_ROOT . '/assets/' . $min)) {
            $path = $min;
            $file = FS_ROOT . '/assets/' . $min;
        }
    }
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . $path) . '?v=' . $v;
}

/** URL to an uploaded file or a bundled asset path. */
function upload_url(?string $path, string $fallback = 'assets/images/placeholder.svg'): string
{
    if (!$path) {
        return url($fallback);
    }
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    if (strpos($path, 'assets/') === 0) {
        return url($path);
    }
    return url('uploads/' . ltrim($path, '/'));
}

function redirect(string $to, int $code = 302): void
{
    if (!preg_match('#^https?://#', $to) && strpos($to, '/') !== 0) {
        $to = url($to);
    }
    header('Location: ' . $to, true, $code);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/** Trimmed string input from POST/GET. */
function input(string $key, $default = '', string $src = 'any'): string
{
    $v = null;
    if ($src !== 'get' && isset($_POST[$key])) {
        $v = $_POST[$key];
    } elseif ($src !== 'post' && isset($_GET[$key])) {
        $v = $_GET[$key];
    }
    if ($v === null || is_array($v)) {
        return (string) $default;
    }
    return trim((string) $v);
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key, '');
    return ($v === '' || !is_numeric($v)) ? $default : (int) $v;
}

function input_float(string $key, ?float $default = 0.0): ?float
{
    $v = str_replace(',', '', input($key, ''));
    return ($v === '' || !is_numeric($v)) ? $default : round((float) $v, 2);
}

function input_arr(string $key): array
{
    $v = $_POST[$key] ?? $_GET[$key] ?? [];
    return is_array($v) ? $v : [];
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $msg, int $code = 400, array $extra = []): void
{
    json_out(array_merge(['ok' => false, 'error' => $msg], $extra), $code);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ------------------------------------------------------------------
 * Settings (key/value table, cached per request)
 * ------------------------------------------------------------------ */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $r) {
            $cache[$r['setting_key']] = $r['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function setting_set(string $key, $value): void
{
    db_exec('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, (string) $value]);
    settings_all(true);
}

/* ------------------------------------------------------------------
 * Formatting
 * ------------------------------------------------------------------ */
function money($amount, bool $symbol = true): string
{
    $amount = (float) $amount;
    $dec = (abs($amount - round($amount)) < 0.005) ? 0 : 2;
    $s = number_format($amount, $dec);
    return $symbol ? setting('currency_symbol', 'Rs.') . ' ' . $s : $s;
}

function fmt_date(?string $dt, string $format = 'd M Y'): string
{
    return $dt ? date($format, strtotime($dt)) : '';
}

function fmt_datetime(?string $dt): string
{
    return fmt_date($dt, 'd M Y, h:i A');
}

function time_ago(string $dt): string
{
    $d = time() - strtotime($dt);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . ' min ago';
    if ($d < 86400) return floor($d / 3600) . ' hr ago';
    if ($d < 604800) return floor($d / 86400) . ' days ago';
    return fmt_date($dt);
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = str_replace(['&', '+'], [' and ', ' plus '], $text);
    if (function_exists('transliterator_transliterate')) {
        $t = @transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        if ($t) {
            $text = $t;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? substr($text, 0, 150) : 'item-' . substr(md5(uniqid('', true)), 0, 6);
}

/** Generate a slug unique in $table.slug (optionally ignoring $ignoreId). */
function unique_slug(string $table, string $base, int $ignoreId = 0): string
{
    $allowed = ['products', 'categories', 'brands', 'pages', 'promotions'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Bad table');
    }
    $slug = slugify($base);
    $try = $slug;
    $i = 2;
    while (db_val("SELECT id FROM `$table` WHERE slug = ? AND id <> ? LIMIT 1", [$try, $ignoreId])) {
        $try = $slug . '-' . $i++;
    }
    return $try;
}

function str_limit(string $s, int $len = 120): string
{
    $s = trim(preg_replace('/\s+/', ' ', strip_tags($s)));
    return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len - 1)) . '…' : $s;
}

/** Normalise a Pakistani mobile number into 03XXXXXXXXX form when possible. */
function normalize_phone(string $p): string
{
    $d = preg_replace('/\D+/', '', $p);
    if (strpos($d, '92') === 0 && strlen($d) === 12) {
        $d = '0' . substr($d, 2);
    } elseif (strlen($d) === 10 && $d[0] === '3') {
        $d = '0' . $d;
    }
    return $d;
}

function valid_phone(string $p): bool
{
    $d = preg_replace('/\D+/', '', $p);
    return strlen($d) >= 10 && strlen($d) <= 13;
}

/** International format for WhatsApp links (92XXXXXXXXXX). */
function phone_intl(string $p): string
{
    $d = normalize_phone($p);
    return (strlen($d) === 11 && $d[0] === '0') ? '92' . substr($d, 1) : $d;
}

/* ------------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------------ */
function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function render_flashes(): string
{
    $out = '';
    foreach (flashes() as $f) {
        $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
        $out .= '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">' . e($f['msg'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    return $out;
}

/** Remember old form input after a failed POST. */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* ------------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------------ */
function paginate(int $total, int $perPage, ?int $page = null): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = $page ?? max(1, input_int('page', 1));
    $page = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Current URL with modified query params. */
function url_with(array $params, ?string $path = null): string
{
    $q = array_merge($_GET, $params);
    unset($q['route']);
    foreach ($q as $k => $v) {
        if ($v === null || $v === '' || ($k === 'page' && (int) $v === 1)) {
            unset($q[$k]);
        }
    }
    $path = $path ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $path . ($q ? '?' . http_build_query($q) : '');
}

function render_pagination(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $h = '<nav aria-label="Pagination"><ul class="pagination justify-content-center flex-wrap">';
    $cur = $p['page'];
    $h .= '<li class="page-item' . ($cur <= 1 ? ' disabled' : '') . '"><a class="page-link" rel="prev" href="' . e(url_with(['page' => $cur - 1])) . '">&laquo;</a></li>';
    $start = max(1, $cur - 2);
    $end = min($p['pages'], $cur + 2);
    if ($start > 1) {
        $h .= '<li class="page-item"><a class="page-link" href="' . e(url_with(['page' => 1])) . '">1</a></li>';
        if ($start > 2) $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $h .= '<li class="page-item' . ($i === $cur ? ' active' : '') . '"><a class="page-link" href="' . e(url_with(['page' => $i])) . '">' . $i . '</a></li>';
    }
    if ($end < $p['pages']) {
        if ($end < $p['pages'] - 1) $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $h .= '<li class="page-item"><a class="page-link" href="' . e(url_with(['page' => $p['pages']])) . '">' . $p['pages'] . '</a></li>';
    }
    $h .= '<li class="page-item' . ($cur >= $p['pages'] ? ' disabled' : '') . '"><a class="page-link" rel="next" href="' . e(url_with(['page' => $cur + 1])) . '">&raquo;</a></li>';
    return $h . '</ul></nav>';
}

/** Status label + badge colour for orders. */
function order_statuses(): array
{
    return [
        'draft'            => ['Draft', 'secondary'],
        'pending'          => ['Pending', 'warning'],
        'confirmed'        => ['Confirmed', 'info'],
        'processing'       => ['Processing', 'primary'],
        'ready_for_pickup' => ['Ready for Pickup', 'teal'],
        'out_for_delivery' => ['Out for Delivery', 'indigo'],
        'completed'        => ['Completed', 'success'],
        'cancelled'        => ['Cancelled', 'danger'],
    ];
}

function status_badge(string $status): string
{
    $s = order_statuses()[$status] ?? [ucfirst($status), 'secondary'];
    return '<span class="badge badge-status bg-' . $s[1] . '">' . e($s[0]) . '</span>';
}

function status_label(string $status): string
{
    return order_statuses()[$status][0] ?? ucfirst($status);
}

function units_list(): array
{
    return ['piece' => 'Piece', 'kg' => 'Kg', 'gram' => 'Gram', 'litre' => 'Litre', 'ml' => 'ml', 'packet' => 'Packet',
        'dozen' => 'Dozen', 'box' => 'Box', 'pack' => 'Pack', 'bottle' => 'Bottle', 'set' => 'Set', 'ream' => 'Ream', 'book' => 'Book'];
}

/** Tiny file cache for expensive lookups (menu tree, search vocabulary). */
function cache_get(string $key, int $ttl, callable $producer)
{
    $dir = FS_ROOT . '/logs/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $key) . '.cache';
    if (is_file($file) && filemtime($file) > time() - $ttl) {
        $data = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
        if ($data !== false) {
            return $data;
        }
    }
    $data = $producer();
    @file_put_contents($file, serialize($data), LOCK_EX);
    return $data;
}

function cache_clear(string $prefix = ''): void
{
    foreach (glob(FS_ROOT . '/logs/cache/' . $prefix . '*.cache') ?: [] as $f) {
        @unlink($f);
    }
}

function log_error(string $msg): void
{
    error_log('[fahim] ' . $msg);
}
