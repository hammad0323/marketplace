<?php
/**
 * Shared helpers used by the public site and the admin panel.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

/* ------------------------------------------------------------------
 * Output & URLs
 * ---------------------------------------------------------------- */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Base URL of the site (no trailing slash), auto-detected when BASE_URL is empty. */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (BASE_URL !== '') {
        return $base = rtrim(BASE_URL, '/');
    }
    $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    $root   = str_replace('\\', '/', realpath(ROOT_PATH));
    $depth  = 0;
    if ($script !== '' && str_starts_with($script, $root . '/')) {
        $depth = substr_count(substr($script, strlen($root) + 1), '/');
    }
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    for ($i = 0; $i < $depth; $i++) {
        $dir = dirname($dir);
    }
    return $base = rtrim($dir, '/.');
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT_PATH . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** URL for an uploaded file, or for a bundled asset path, or '' when empty. */
function media(?string $file): string
{
    if (!$file) {
        return '';
    }
    if (preg_match('~^(https?:)?//~', $file)) {
        return $file;
    }
    if (str_starts_with($file, 'assets/')) {
        return url($file);
    }
    return url('uploads/' . rawurlencode($file));
}

function redirect(string $to): never
{
    header('Location: ' . (preg_match('~^https?://~', $to) ? $to : url($to)));
    exit;
}

function current_page(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
}

/* ------------------------------------------------------------------
 * Database helpers
 * ---------------------------------------------------------------- */

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function val(string $sql, array $params = [])
{
    return q($sql, $params)->fetchColumn();
}

/* ------------------------------------------------------------------
 * Settings (key/value table editable from the admin panel)
 * ---------------------------------------------------------------- */

function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        try {
            foreach (rows('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = $r['svalue'];
            }
        } catch (PDOException $e) {
            $cache = [];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $s = settings();
    return isset($s[$key]) && $s[$key] !== '' ? $s[$key] : $default;
}

function setting_on(string $key): bool
{
    $s = settings();
    return !isset($s[$key]) || $s[$key] === '1';
}

function save_setting(string $key, string $value): void
{
    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $value]);
}

/* ------------------------------------------------------------------
 * Content helpers
 * ---------------------------------------------------------------- */

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    return trim($text, '-') ?: 'item';
}

/** Split a textarea (one item per line) into a clean array. */
function lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $text)), 'strlen'));
}

function money($amount): string
{
    $symbol = setting('currency_symbol', '$');
    $n = (float) $amount;
    $formatted = floor($n) == $n ? number_format($n) : number_format($n, 2);
    return setting('currency_position', 'before') === 'after' ? $formatted . ' ' . $symbol : $symbol . $formatted;
}

function excerpt(?string $html, int $len = 150): string
{
    $text = trim(preg_replace('~\s+~', ' ', strip_tags((string) $html)));
    return mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len)) . '…' : $text;
}

function initials(string $name): string
{
    $parts = preg_split('~\s+~', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: 'W';
}

function nice_date(?string $date): string
{
    return $date ? date('M j, Y', strtotime($date)) : '';
}

/** Allow-list of tags for rich text written in the admin panel. */
function rich(?string $html): string
{
    $html = (string) $html;
    $html = preg_replace('~<(script|style|iframe|object|embed)[^>]*>.*?</\1>~is', '', $html);
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><img><span><hr><code><pre>');
    $html = preg_replace('~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $html);
    $html = preg_replace('~(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2~i', '$1="#"', $html);
    return $html;
}

/* ------------------------------------------------------------------
 * CSRF & flash
 * ---------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string) $_POST['csrf']);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ------------------------------------------------------------------
 * Admin auth
 * ---------------------------------------------------------------- */

function admin(): ?array
{
    static $admin = false;
    if ($admin === false) {
        $admin = !empty($_SESSION['admin_id'])
            ? row('SELECT id, name, email FROM admins WHERE id = ?', [$_SESSION['admin_id']])
            : null;
    }
    return $admin;
}

function require_admin(): array
{
    $a = admin();
    if (!$a) {
        redirect('admin/login.php');
    }
    return $a;
}

/* ------------------------------------------------------------------
 * Uploads
 * ---------------------------------------------------------------- */

/**
 * Stores an uploaded image from $_FILES[$field]. Returns the stored
 * file name, null when nothing was uploaded, or throws on bad input.
 */
function upload_image(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $f['error'] . '). The file may be too large.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'avif'];
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Only image files are allowed (' . implode(', ', $allowed) . ').');
    }
    if ($f['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Image is larger than 8 MB.');
    }
    if ($ext === 'svg') {
        $svg = (string) file_get_contents($f['tmp_name']);
        if (preg_match('~<script|on\w+\s*=|javascript:~i', $svg)) {
            throw new RuntimeException('That SVG contains scripts and was rejected.');
        }
    } elseif ($ext !== 'ico' && @getimagesize($f['tmp_name']) === false) {
        throw new RuntimeException('That file is not a valid image.');
    }
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_PATH . '/' . $name)) {
        throw new RuntimeException('Could not save the upload. Check that the uploads/ folder is writable.');
    }
    return $name;
}

/* ------------------------------------------------------------------
 * Public data shortcuts
 * ---------------------------------------------------------------- */

function active(string $table, string $extra = '', array $params = []): array
{
    $allowed = ['services', 'package_categories', 'packages', 'portfolio', 'team', 'testimonials',
        'faqs', 'posts', 'clients', 'stats', 'process_steps'];
    if (!in_array($table, $allowed, true)) {
        return [];
    }
    return rows("SELECT * FROM `$table` WHERE is_active = 1 $extra", $params);
}

function social_links(): array
{
    $map = [
        'facebook'  => 'fa-brands fa-facebook-f',
        'linkedin'  => 'fa-brands fa-linkedin-in',
        'instagram' => 'fa-brands fa-instagram',
        'twitter'   => 'fa-brands fa-x-twitter',
        'youtube'   => 'fa-brands fa-youtube',
        'behance'   => 'fa-brands fa-behance',
        'github'    => 'fa-brands fa-github',
        'tiktok'    => 'fa-brands fa-tiktok',
    ];
    $out = [];
    foreach ($map as $key => $icon) {
        $link = setting('social_' . $key);
        if ($link !== '') {
            $out[] = ['name' => ucfirst($key), 'url' => $link, 'icon' => $icon];
        }
    }
    return $out;
}

function whatsapp_link(string $text = ''): string
{
    $num = preg_replace('~\D~', '', setting('whatsapp'));
    return $num ? 'https://wa.me/' . $num . ($text !== '' ? '?text=' . rawurlencode($text) : '') : '';
}

/** Renders an image, or a branded gradient placeholder when no image is set. */
function thumb(?string $file, string $alt, string $label = '', string $icon = 'fa-solid fa-code', string $class = ''): string
{
    if ($file) {
        return '<img class="' . e($class) . '" src="' . e(media($file)) . '" alt="' . e($alt) . '" loading="lazy">';
    }
    $hue = abs(crc32($alt)) % 5;
    return '<div class="ph ph-' . $hue . ' ' . e($class) . '" role="img" aria-label="' . e($alt) . '">'
        . '<i class="' . e($icon) . '"></i>' . ($label !== '' ? '<span>' . e($label) . '</span>' : '') . '</div>';
}
