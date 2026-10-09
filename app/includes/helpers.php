<?php
/**
 * General-purpose helpers: escaping, URLs, input, formatting, flash messages.
 */

/** HTML-escape for output. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside a JSON <script> block. */
function json_attr($value): string
{
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    static $p = null;
    if ($p === null) {
        $p = rtrim((string) parse_url(APP_URL, PHP_URL_PATH), '/');
    }
    return $p;
}

/** Absolute public URL for an app path ("/product/x" or "product/x"). */
function url(string $path = '', array $query = []): string
{
    $path = ltrim($path, '/');
    $u = rtrim(APP_URL, '/') . '/' . $path;
    if ($query) {
        $u .= (strpos($u, '?') === false ? '?' : '&') . http_build_query($query);
    }
    return $u;
}

/** Root-relative URL (no host) — used for links inside the site. */
function path_url(string $path = '', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= (strpos($u, '?') === false ? '?' : '&') . http_build_query($query);
    }
    return $u;
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return path_url('assets/' . ltrim($path, '/')) . $v;
}

/** URL for a stored media path (uploads/..., assets/...) or an absolute URL. */
function media_url(?string $path, string $fallback = 'assets/img/placeholder.svg'): string
{
    $path = trim((string) $path);
    if ($path === '') {
        $path = $fallback;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return path_url($path);
}

function abs_media_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '' || preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url($path);
}

function admin_url(string $path = '', array $query = []): string
{
    return path_url('admin/' . ltrim($path, '/'), $query);
}

function product_url(array $p): string
{
    return path_url('product/' . $p['slug']);
}

function category_url(array $c): string
{
    return path_url('category/' . $c['slug']);
}

function redirect(string $to, int $code = 302): void
{
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

/** Trimmed string input from POST (or GET when $src = 'get'). */
function input(string $key, $default = '', string $src = 'post'): string
{
    $bag = $src === 'get' ? $_GET : $_POST;
    $v = $bag[$key] ?? $default;
    if (is_array($v)) {
        return (string) $default;
    }
    return trim(str_replace("\0", '', (string) $v));
}

function input_int(string $key, int $default = 0, string $src = 'post'): int
{
    $v = input($key, '', $src);
    return is_numeric($v) ? (int) $v : $default;
}

function input_float(string $key, ?float $default = null, string $src = 'post'): ?float
{
    $v = input($key, '', $src);
    return is_numeric($v) ? round((float) $v, 2) : $default;
}

function input_bool(string $key, string $src = 'post'): int
{
    $bag = $src === 'get' ? $_GET : $_POST;
    return !empty($bag[$key]) ? 1 : 0;
}

function input_array(string $key, string $src = 'post'): array
{
    $bag = $src === 'get' ? $_GET : $_POST;
    return isset($bag[$key]) && is_array($bag[$key]) ? $bag[$key] : [];
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function user_agent(): string
{
    return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Currency formatting, driven by settings. */
function money($amount): string
{
    $symbol = setting('currency_symbol', 'Rs.');
    $decimals = (int) setting('currency_decimals', '0');
    $formatted = number_format((float) $amount, $decimals);
    return setting('currency_position', 'before') === 'after'
        ? $formatted . ' ' . $symbol
        : $symbol . ' ' . $formatted;
}

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '';
    }
    $fmt = setting('date_format', 'd M Y') . ($withTime ? ' ' . setting('time_format', 'h:i A') : '');
    return date($fmt, strtotime($date));
}

function slugify(string $text, int $max = 120): string
{
    $text = mb_strtolower(trim($text), 'UTF-8');
    if (function_exists('transliterator_transliterate')) {
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text) ?: $text;
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');
    return substr($text, 0, $max) ?: 'item';
}

/** Ensure a slug is unique in $table (ignoring $ignoreId). */
function unique_slug(string $table, string $slug, int $ignoreId = 0): string
{
    $base = $slug;
    $i = 2;
    while (db_val('SELECT COUNT(*) FROM ' . db_ident($table) . ' WHERE slug = ? AND id <> ?', [$slug, $ignoreId]) > 0) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function excerpt(?string $html, int $len = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));
    if (mb_strlen($text) <= $len) {
        return $text;
    }
    $cut = mb_substr($text, 0, $len);
    $space = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $space ?: $len), ' ,.;:') . '…';
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($email) <= 190;
}

/** Loose international / Pakistani phone validation. */
function valid_phone(string $phone): bool
{
    $digits = preg_replace('/[^0-9]/', '', $phone);
    return strlen($digits) >= 10 && strlen($digits) <= 15 && preg_match('/^[+0-9 ()\-]+$/', $phone);
}

function valid_hex(string $hex): bool
{
    return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $hex);
}

/** Only allow site-relative paths or http(s) URLs in admin-entered links. */
function safe_link(?string $link, string $fallback = '#'): string
{
    $link = trim((string) $link);
    if ($link === '') {
        return $fallback;
    }
    if (preg_match('#^(https?://|mailto:|tel:)#i', $link)) {
        return $link;
    }
    if ($link[0] === '/' && (strlen($link) === 1 || $link[1] !== '/')) {
        return base_path() . $link;
    }
    if ($link[0] === '#') {
        return $link;
    }
    return $fallback;
}

// ---- Flash messages ---------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

/** Remember submitted form values across a redirect. */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['csrf_token']);
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ---- Views ------------------------------------------------------------------

/** Render a view file from app/views with extracted variables. */
function view(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_PATH . '/views/' . $name . '.php';
}

function partial(string $name, array $vars = []): void
{
    view('partials/' . $name, $vars);
}

function render_page(string $view, array $vars = [], array $meta = []): void
{
    $GLOBALS['page_meta'] = array_merge($GLOBALS['page_meta'] ?? [], $meta);
    partial('header', $vars);
    view($view, $vars);
    partial('footer', $vars);
}

/** Simple pagination descriptor. */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    return [
        'total' => $total,
        'per_page' => $perPage,
        'page' => $page,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}

/** Current request query with overrides — for filter/sort/page links. */
function query_with(array $overrides, array $drop = []): string
{
    $q = $_GET;
    unset($q['_route']);
    foreach ($drop as $d) {
        unset($q[$d]);
    }
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    if (isset($q['page']) && (int) $q['page'] <= 1) {
        unset($q['page']);
    }
    return $q ? '?' . http_build_query($q) : '';
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

/** Bootstrap colour for an order/payment status badge. */
function status_color(string $status): string
{
    $map = [
        'pending' => 'warning', 'confirmed' => 'info', 'processing' => 'primary', 'shipped' => 'primary',
        'delivered' => 'success', 'cancelled' => 'secondary', 'refunded' => 'dark', 'on_hold' => 'warning',
        'unpaid' => 'warning', 'paid' => 'success', 'failed' => 'danger', 'partially_refunded' => 'dark',
        'published' => 'success', 'draft' => 'secondary', 'inactive' => 'dark', 'approved' => 'success',
        'rejected' => 'danger', 'subscribed' => 'success', 'unsubscribed' => 'secondary', 'new' => 'primary',
        'active' => 'success', 'disabled' => 'secondary', 'in_stock' => 'success', 'out_of_stock' => 'danger',
        'preorder' => 'info', 'low_stock' => 'warning',
    ];
    return $map[$status] ?? 'secondary';
}

function app_log(string $channel, string $message, array $context = []): void
{
    $line = '[' . date('c') . "] $channel: $message" . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '') . PHP_EOL;
    @file_put_contents(STORAGE_PATH . '/logs/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
}
