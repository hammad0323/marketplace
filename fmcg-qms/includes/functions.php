<?php
/**
 * General-purpose helper functions.
 */
require_once __DIR__ . '/permissions.php';

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function slugify(string $text): string
{
    $text = preg_replace('/[^a-zA-Z0-9]+/', '-', trim($text));
    $text = strtolower(trim($text, '-'));
    return $text ?: 'item-' . substr(md5(uniqid('', true)), 0, 8);
}

function fmt_date(?string $date, string $format = 'd M Y'): string
{
    if (!$date || $date === '0000-00-00') {
        return '-';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '-';
}

function fmt_datetime(?string $date, string $format = 'd M Y, h:i A'): string
{
    return fmt_date($date, $format);
}

function fmt_number($num, int $decimals = 2): string
{
    if ($num === null || $num === '') {
        return '-';
    }
    return number_format((float)$num, $decimals);
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return fmt_date($datetime);
}

function paginate(int $totalRows, int $page, int $perPage = 20): array
{
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return compact('totalRows', 'page', 'perPage', 'totalPages', 'offset');
}

function pagination_links(int $page, int $totalPages, string $baseUrl): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . max(1, $page - 1) . '">Prev</a></li>';
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item ' . ($i === $page ? 'active' : '') . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }
    $html .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . min($totalPages, $page + 1) . '">Next</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

function severity_badge(?string $severity): string
{
    $severity = $severity ?: 'observation';
    $map = [
        'critical' => 'bg-danger',
        'high' => 'bg-danger-subtle text-danger',
        'medium' => 'bg-warning-subtle text-warning',
        'low' => 'bg-info-subtle text-info',
        'observation' => 'bg-secondary-subtle text-secondary',
    ];
    $cls = $map[$severity] ?? 'bg-secondary';
    return '<span class="badge ' . $cls . ' text-capitalize">' . out($severity) . '</span>';
}

function status_badge(?string $status): string
{
    $status = $status ?: 'open';
    $map = [
        'open' => 'bg-danger-subtle text-danger', 'detected' => 'bg-danger-subtle text-danger',
        'assigned' => 'bg-info-subtle text-info', 'investigation' => 'bg-info-subtle text-info',
        'in_progress' => 'bg-warning-subtle text-warning', 'containment' => 'bg-warning-subtle text-warning',
        'root_cause' => 'bg-warning-subtle text-warning', 'corrective_action' => 'bg-primary-subtle text-primary',
        'preventive_action' => 'bg-primary-subtle text-primary', 'pending_verification' => 'bg-primary-subtle text-primary',
        'verification' => 'bg-primary-subtle text-primary', 'effective' => 'bg-success-subtle text-success',
        'closed' => 'bg-success-subtle text-success', 'rejected' => 'bg-danger-subtle text-danger',
        'active' => 'bg-success-subtle text-success', 'inactive' => 'bg-secondary-subtle text-secondary',
        'submitted' => 'bg-success-subtle text-success', 'pending' => 'bg-warning-subtle text-warning',
        'missed' => 'bg-danger-subtle text-danger', 'pass' => 'bg-success-subtle text-success',
        'fail' => 'bg-danger-subtle text-danger', 'sent' => 'bg-success-subtle text-success',
        'failed' => 'bg-danger-subtle text-danger', 'accepted' => 'bg-success-subtle text-success',
        'conditional' => 'bg-warning-subtle text-warning',
    ];
    $cls = $map[$status] ?? 'bg-secondary-subtle text-secondary';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="badge ' . $cls . '">' . out($label) . '</span>';
}

function rag_badge(string $rag): string
{
    $map = ['green' => 'bg-success', 'amber' => 'bg-warning', 'red' => 'bg-danger'];
    $cls = $map[$rag] ?? 'bg-secondary';
    return '<span class="rag-dot ' . $cls . '" title="' . ucfirst($rag) . '"></span>';
}

function rag_from_thresholds(float $value, float $greenMin, float $amberMin): string
{
    if ($value >= $greenMin) return 'green';
    if ($value >= $amberMin) return 'amber';
    return 'red';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Builds an app-relative URL and strips ".php" from the path (before an optional "?query"),
 * so every link/redirect/form the app generates uses clean URLs (e.g. /manager/dashboard
 * instead of /manager/dashboard.php). The .htaccess rewrite rule maps the clean URL back to
 * the real .php file server-side; requesting the .php path directly still works too.
 */
function base_url(string $path = ''): string
{
    $path = preg_replace('/\.php(\?|$)/', '$1', $path);
    return BASE_URL . '/' . ltrim($path, '/');
}

function app_url_for_role(string $role): string
{
    return match ($role) {
        'super_admin' => base_url('admin/dashboard.php'),
        'manager' => base_url('manager/dashboard.php'),
        'employee' => base_url('employee/dashboard.php'),
        default => base_url('index.php'),
    };
}

function generate_code(string $prefix, int $length = 6): string
{
    return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
}

function generate_sequenced_number(int $companyId, string $table, string $column, string $prefix): string
{
    $year = date('Y');
    $count = db_count($table, "company_id = ? AND YEAR(created_at) = ?", 'ii', [$companyId, (int)$year]);
    $next = $count + 1;
    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

function get_platform_setting(string $key, $default = '')
{
    $val = db_val("SELECT setting_value FROM platform_settings WHERE setting_key = ?", [$key]);
    return $val !== null ? $val : $default;
}

function set_platform_setting(string $key, string $value): void
{
    db_exec("INSERT INTO platform_settings (setting_key, setting_value) VALUES (?, ?)
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$key, $value]);
}

function get_company_setting(int $companyId, string $key, $default = '')
{
    $val = db_val("SELECT setting_value FROM company_settings WHERE company_id = ? AND setting_key = ?", [$companyId, $key]);
    return $val !== null ? $val : $default;
}

function set_company_setting(int $companyId, string $key, string $value): void
{
    db_exec("INSERT INTO company_settings (company_id, setting_key, setting_value) VALUES (?, ?, ?)
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$companyId, $key, $value]);
}

/** Branding helpers - always read live from platform_settings so admin changes apply everywhere instantly. */
function app_name(): string
{
    return get_platform_setting('platform_name', APP_NAME_DEFAULT) ?: APP_NAME_DEFAULT;
}

function app_primary_color(): string
{
    $color = get_platform_setting('primary_color', PRIMARY_COLOR_DEFAULT);
    return preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$color) ? $color : PRIMARY_COLOR_DEFAULT;
}

function app_logo_url(): ?string
{
    $logo = get_platform_setting('logo', '');
    return $logo !== '' ? UPLOAD_URL . '/branding/' . $logo : null;
}

function app_favicon_url(): ?string
{
    $favicon = get_platform_setting('favicon', '');
    return $favicon !== '' ? UPLOAD_URL . '/branding/' . $favicon : null;
}

/**
 * Absolute site URL (scheme + host + BASE_URL) for contexts where a path-only URL isn't
 * usable - primarily emails, which are opened outside any browser session. Prefers the
 * canonical "site_url" set by the Super Admin (required for links generated by cron, where
 * no HTTP request/host is available); falls back to the current request's own host.
 */
function app_absolute_url(string $path = ''): string
{
    $configured = trim((string)get_platform_setting('site_url', ''));
    if ($configured !== '') {
        $base = rtrim($configured, '/');
    } elseif (!empty($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') == 443 ? 'https' : 'http';
        $base = $scheme . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
    } else {
        $base = BASE_URL; // CLI/cron with no site_url configured: best effort, path-only
    }
    $path = preg_replace('/\.php(\?|$)/', '$1', $path);
    return $base . '/' . ltrim($path, '/');
}

function json_decode_safe(?string $json, $default = [])
{
    if (!$json) {
        return $default;
    }
    $decoded = json_decode($json, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}
