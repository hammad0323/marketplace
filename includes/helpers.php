<?php
/**
 * General helpers: URLs, formatting, requests, flash messages, pagination.
 */

/** Absolute URL for a site path: url('product/x') → https://site/product/x */
function url(string $path = ''): string
{
    if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
        return $path;
    }
    if ($path !== '' && $path[0] === '#') {
        return $path;
    }
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}

/** Root-relative path (no host), useful for redirects table comparisons. */
function path_url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = UC_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** Image URL for an upload path, a remote URL, or the branded placeholder. */
function img_url(?string $path, string $fallback = 'assets/images/placeholder.svg'): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return url($fallback);
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, 'assets/')) {
        return url($path);
    }
    return url('uploads/' . ltrim($path, '/'));
}

/**
 * Remote sample photography is served from images.unsplash.com: request a size
 * appropriate for where the image is shown. Uploaded images are returned as is.
 */
function img_sized(?string $path, int $width): string
{
    $src = img_url($path);
    if (str_contains($src, 'images.unsplash.com')) {
        $src = preg_replace('/([?&])w=\d+/', '$1w=' . $width, $src);
        if (!str_contains($src, 'w=')) {
            $src .= (str_contains($src, '?') ? '&' : '?') . 'w=' . $width;
        }
    }
    return $src;
}

function money($amount, bool $withSymbol = true): string
{
    $amount = (float) $amount;
    $decimals = (int) setting('currency_decimals', 0);
    $num = number_format($amount, $decimals);
    if (!$withSymbol) {
        return $num;
    }
    $symbol = setting('currency_symbol', 'Rs.');
    return setting('currency_position', 'before') === 'after' ? $num . ' ' . $symbol : $symbol . ' ' . $num;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text);
    return trim(preg_replace('/-+/', '-', $text), '-') ?: 'item';
}

/** Returns a slug that is unique in $table.slug (excluding $ignoreId). */
function unique_slug(string $table, string $slug, int $ignoreId = 0): string
{
    $base = slugify($slug);
    $candidate = $base;
    $i = 2;
    while (db_val('SELECT id FROM ' . db_ident($table) . ' WHERE slug = ? AND id <> ? LIMIT 1', [$candidate, $ignoreId])) {
        $candidate = $base . '-' . $i++;
    }
    return $candidate;
}

function redirect(string $to, int $code = 302): never
{
    header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to)), true, $code);
    exit;
}

function redirect_back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref && str_starts_with($ref, rtrim(SITE_URL, '/'))) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Trimmed string from POST/GET. */
function input(string $key, $default = '', string $source = 'request'): string
{
    $bag = $source === 'post' ? $_POST : ($source === 'get' ? $_GET : $_REQUEST);
    $v = $bag[$key] ?? $default;
    return is_scalar($v) ? trim((string) $v) : (string) $default;
}

function input_int(string $key, int $default = 0): int
{
    $v = $_REQUEST[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

function input_float(string $key, ?float $default = null): ?float
{
    $v = $_REQUEST[$key] ?? null;
    if ($v === null || $v === '' || !is_numeric($v)) {
        return $default;
    }
    return round((float) $v, 2);
}

function input_bool(string $key): int
{
    return !empty($_REQUEST[$key]) ? 1 : 0;
}

function input_array(string $key): array
{
    $v = $_REQUEST[$key] ?? [];
    return is_array($v) ? $v : [];
}

function nullable(string $v): ?string
{
    return $v === '' ? null : $v;
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190;
}

/** Pakistani + international phone numbers: digits, spaces, +, -, 7–20 digits. */
function valid_phone(string $phone): bool
{
    $digits = preg_replace('/\D/', '', $phone);
    return (bool) preg_match('/^[0-9+\-\s()]{7,25}$/', $phone) && strlen($digits) >= 7 && strlen($digits) <= 15;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

function str_limit(?string $text, int $len = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    return mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len - 1)) . '…' : $text;
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    return $date ? date($format, strtotime($date)) : '';
}

function format_datetime(?string $date): string
{
    return format_date($date, 'd M Y, h:i A');
}

// ---------------------------------------------------------------------
//  Flash messages & old input
// ---------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function remember_input(array $data): void
{
    $_SESSION['_old'] = $data;
}

function old(string $key, $default = ''): string
{
    if (isset($_SESSION['_old'][$key]) && is_scalar($_SESSION['_old'][$key])) {
        return (string) $_SESSION['_old'][$key];
    }
    return (string) $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ---------------------------------------------------------------------
//  Pagination
// ---------------------------------------------------------------------
function paginate(int $total, int $perPage, ?int $page = null): array
{
    $pages = max(1, (int) ceil($total / max(1, $perPage)));
    $page = max(1, min($pages, $page ?? input_int('page', 1)));
    return ['total' => $total, 'per_page' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

/** Current query string with some keys replaced. */
function query_with(array $replace, array $remove = []): string
{
    $q = array_merge($_GET, $replace);
    unset($q['route']);
    foreach ($remove as $r) {
        unset($q[$r]);
    }
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null && $v !== []);
    return $q ? '?' . http_build_query($q) : '';
}

function pagination_links(array $p, string $baseUrl = ''): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $html = '<nav aria-label="Pagination"><ul class="pagination uc-pagination">';
    $range = [];
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($i === 1 || $i === $p['pages'] || abs($i - $p['page']) <= 2) {
            $range[] = $i;
        } elseif (end($range) !== '…') {
            $range[] = '…';
        }
    }
    if ($p['page'] > 1) {
        $html .= '<li class="page-item"><a class="page-link" rel="prev" href="' . e($baseUrl . query_with(['page' => $p['page'] - 1])) . '" aria-label="Previous">&lsaquo;</a></li>';
    }
    foreach ($range as $i) {
        if ($i === '…') {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        } else {
            $active = $i === $p['page'] ? ' active" aria-current="page' : '';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($baseUrl . query_with(['page' => $i])) . '">' . $i . '</a></li>';
        }
    }
    if ($p['page'] < $p['pages']) {
        $html .= '<li class="page-item"><a class="page-link" rel="next" href="' . e($baseUrl . query_with(['page' => $p['page'] + 1])) . '" aria-label="Next">&rsaquo;</a></li>';
    }
    return $html . '</ul></nav>';
}

/** Renders an include with local variables (used for components and sections). */
function partial(string $file, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    include UC_ROOT . '/includes/' . $file . '.php';
}

function render_stars(float $rating, string $class = ''): string
{
    $html = '<span class="uc-stars ' . e($class) . '" aria-label="' . e(number_format($rating, 1)) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $icon = $rating >= $i ? 'bi-star-fill' : ($rating >= $i - 0.5 ? 'bi-star-half' : 'bi-star');
        $html .= '<i class="bi ' . $icon . '" aria-hidden="true"></i>';
    }
    return $html . '</span>';
}

function log_message(string $file, string $message): void
{
    $dir = UC_ROOT . '/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents($dir . '/' . basename($file) . '.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/** Navigation menu as a tree (header, footer_shop, footer_help, footer_about). */
function nav_items(string $menu): array
{
    static $cache = [];
    if (!isset($cache[$menu])) {
        $rows = db_all('SELECT * FROM navigation_items WHERE menu = ? AND is_active = 1 ORDER BY sort_order, id', [$menu]);
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[(int) $r['parent_id']][] = $r;
        }
        $build = function (int $parent) use (&$build, $byParent) {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $r) {
                $r['children'] = $build((int) $r['id']);
                $out[] = $r;
            }
            return $out;
        };
        $cache[$menu] = $build(0);
    }
    return $cache[$menu];
}

/** URL for a navigation item: "/", relative paths, or absolute links. */
function nav_url(array $item): string
{
    $u = trim((string) $item['url']);
    if ($u === '' || $u === '/') {
        return url();
    }
    return url($u);
}

/** Is the current request inside this link's path? (for active states) */
function nav_active(array $item): bool
{
    $u = trim((string) $item['url'], '/');
    $path = trim((string) ($GLOBALS['ROUTE']['path'] ?? ''), '/');
    if ($u === '') {
        return $path === '';
    }
    return $path === $u || str_starts_with($path, $u . '/');
}
