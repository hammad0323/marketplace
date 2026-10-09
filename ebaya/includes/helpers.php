<?php
/**
 * General helpers: escaping, URLs, money, flash messages, CSRF, rate limiting,
 * sessions and a small allow-list HTML sanitizer for admin-written content.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Site-relative URL: url('product/x') → /base/product/x */
function url(string $path = ''): string
{
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:') || str_starts_with($path, '#')) {
        return $path;
    }
    return BASE_PATH . '/' . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) return $path;
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

function admin_url(string $path = ''): string
{
    return url('admin/' . ltrim($path, '/'));
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Image path stored in DB (uploads/... or assets/...) → URL, with placeholder fallback. */
function img_url(?string $path, string $fallback = 'assets/img/placeholder.svg'): string
{
    $path = trim((string)$path);
    if ($path === '') return url($fallback);
    if (preg_match('#^https?://#i', $path)) return $path;
    return url($path);
}

function currency_symbol(): string
{
    return setting('currency_symbol', 'Rs.');
}

function money($amount): string
{
    return currency_symbol() . ' ' . number_format((float)$amount, 0, '.', ',');
}

function money2(float $v): float
{
    return round($v, 2);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'item';
}

function str_limit(?string $s, int $n): string
{
    $s = trim(strip_tags((string)$s));
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}

function redirect(string $to, int $code = 302): void
{
    if (!preg_match('#^https?://#i', $to) && !str_starts_with($to, '/')) {
        $to = url($to);
    }
    header('Location: ' . $to, true, $code);
    exit;
}

/** Only allow same-site relative redirect targets (prevents open redirects). */
function safe_return(?string $to, string $default = ''): string
{
    $to = (string)$to;
    if ($to !== '' && str_starts_with($to, '/') && !str_starts_with($to, '//') && !str_contains($to, '\\')) {
        return $to;
    }
    return url($default);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

// ---------------------------------------------------------------------
// Sessions
// ---------------------------------------------------------------------
function session_bootstrap(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '86400');
    session_name('ebaya_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (BASE_PATH ?: '') . '/',
        'secure' => FORCE_HTTPS && is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Bind the session loosely to the browser to make stolen IDs less useful.
    $fp = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (!isset($_SESSION['_fp'])) {
        $_SESSION['_fp'] = $fp;
    } elseif (!hash_equals($_SESSION['_fp'], $fp)) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_fp'] = $fp;
    }
}

// ---------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------
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

/** Keep posted values for re-rendering a form after a validation error. */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

function remember_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['csrf_token']);
    $_SESSION['_old'] = $data;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function csrf_check(): void
{
    if (!csrf_valid()) {
        if (is_ajax()) json_out(['ok' => false, 'message' => 'Your session expired. Please refresh the page and try again.'], 419);
        http_response_code(419);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------------------
// Rate limiting (fixed window, stored in MySQL so it works on shared hosting)
// ---------------------------------------------------------------------
function rate_limit(string $bucket, int $max, int $windowSeconds, ?string $id = null): bool
{
    $key = substr($bucket . ':' . ($id ?? client_ip()), 0, 190);
    $now = time();
    if (random_int(1, 200) === 1) {
        // Opportunistic housekeeping (shared hosting often has no cron).
        db_exec('DELETE FROM rate_limits WHERE window_start < ?', [$now - 86400]);
        db_exec('DELETE FROM carts WHERE customer_id IS NULL AND updated_at < DATE_SUB(NOW(), INTERVAL 60 DAY)');
        db_exec('DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
    }
    $row = db_one('SELECT hits, window_start FROM rate_limits WHERE rl_key = ?', [$key]);
    if (!$row || $row['window_start'] + $windowSeconds < $now) {
        db_exec('REPLACE INTO rate_limits (rl_key, hits, window_start) VALUES (?, 1, ?)', [$key, $now]);
        return true;
    }
    if ((int)$row['hits'] >= $max) {
        return false;
    }
    db_exec('UPDATE rate_limits SET hits = hits + 1 WHERE rl_key = ?', [$key]);
    return true;
}

function rate_limit_or_fail(string $bucket, int $max, int $window, ?string $id = null): void
{
    if (!rate_limit($bucket, $max, $window, $id)) {
        $msg = 'Too many attempts. Please wait a few minutes and try again.';
        if (is_ajax()) json_out(['ok' => false, 'message' => $msg], 429);
        http_response_code(429);
        flash('danger', $msg);
        redirect($_SERVER['HTTP_REFERER'] ?? url());
    }
}

// ---------------------------------------------------------------------
// HTML sanitizer for rich content written in the admin (pages, product
// descriptions). Allow-list of tags/attributes; strips scripts, event
// handlers and javascript: URLs.
// ---------------------------------------------------------------------
function sanitize_html(?string $html): string
{
    $html = trim((string)$html);
    if ($html === '') return '';
    $allowed = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [], 'hr' => [], 'span' => ['class'], 'div' => ['class'],
        'a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'table' => ['class'], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'figure' => [], 'figcaption' => [], 'small' => [],
        'details' => [], 'summary' => [],
    ];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__root');
    if (!$root) return e($html);

    $walk = function (DOMNode $node) use (&$walk, $allowed) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                if (!isset($allowed[$tag])) {
                    $walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                    $attr = $child->attributes->item($a);
                    $name = strtolower($attr->name);
                    if (!in_array($name, $allowed[$tag], true)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                    if (in_array($name, ['href', 'src'], true)) {
                        $v = trim($attr->value);
                        if (!preg_match('#^(https?:|mailto:|tel:|/|\#|[a-z0-9\-_./]+$)#i', $v) || preg_match('#^\s*(javascript|data|vbscript):#i', $v)) {
                            $child->removeAttribute($attr->name);
                        }
                    }
                }
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                $walk($child);
            } elseif ($child instanceof DOMComment) {
                $node->removeChild($child);
            }
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return $out;
}

/** Plain text → paragraphs (used for multi-line admin text fields). */
function nl2p(?string $text): string
{
    $text = trim((string)$text);
    if ($text === '') return '';
    $parts = preg_split("/\R{2,}/", $text);
    return implode('', array_map(fn($p) => '<p>' . nl2br(e($p)) . '</p>', $parts));
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / max(1, $perPage)));
    $page = min(max(1, $page), $pages);
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Current query string with overrides (for filter/sort/pagination links). */
function query_with(array $overrides): string
{
    $q = array_merge($_GET, $overrides);
    unset($q['route']);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '' && $v !== []);
    return $q ? '?' . http_build_query($q) : '';
}

function log_message(string $file, string $msg): void
{
    @file_put_contents(ROOT_PATH . '/storage/logs/' . basename($file) . '.log', '[' . now() . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function status_badge(string $status): string
{
    $map = [
        'pending' => 'warning', 'confirmed' => 'info', 'processing' => 'primary', 'in_production' => 'primary',
        'shipped' => 'info', 'delivered' => 'success', 'cancelled' => 'secondary', 'returned' => 'dark',
        'unpaid' => 'secondary', 'paid' => 'success', 'failed' => 'danger', 'refunded' => 'dark',
        'partially_refunded' => 'dark', 'success' => 'success', 'published' => 'success', 'draft' => 'secondary',
        'inactive' => 'secondary', 'active' => 'success', 'approved' => 'success', 'rejected' => 'danger',
        'subscribed' => 'success', 'unsubscribed' => 'secondary', 'disabled' => 'danger', 'new' => 'warning',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . $cls . ' status-badge">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}
