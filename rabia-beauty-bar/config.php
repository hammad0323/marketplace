<?php
/**
 * Rabia Khan's Beauty Bar & Academy — global bootstrap.
 *
 * Every page requires this ONE file. It starts the session, connects to
 * MySQL and defines the small set of helpers used across the site.
 *
 * TO DEPLOY: edit the four DB_* lines below, import database.sql, done.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '0');          // set to '1' while developing
date_default_timezone_set('Asia/Karachi');

define('DB_HOST', 'localhost');
define('DB_NAME', 'rabia_beauty');
define('DB_USER', 'root');
define('DB_PASS', '');

// Admin pages define BASE as '../' before including this file so that
// asset/upload paths resolve from the admin folder too.
if (!defined('BASE')) {
    define('BASE', '');
}
define('UPLOAD_DIR', __DIR__ . '/uploads/');

/* ------------------------------------------------------------------ */
/*  Database                                                          */
/* ------------------------------------------------------------------ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;max-width:640px;margin:80px auto;padding:24px;border:1px solid #f3c2c5;background:#fcebec;border-radius:8px;color:#8a161d;">'
                . '<h2 style="margin-top:0">Database connection failed</h2>'
                . '<p>Check DB_HOST / DB_NAME / DB_USER / DB_PASS in <code>config.php</code> and make sure <code>database.sql</code> has been imported.</p></div>');
        }
    }
    return $pdo;
}

/** Run a prepared query and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/* ------------------------------------------------------------------ */
/*  Settings (key/value table, editable from admin → Settings)        */
/* ------------------------------------------------------------------ */

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT skey, svalue FROM settings')->fetchAll() as $row) {
            $cache[$row['skey']] = $row['svalue'];
        }
    }
    return isset($cache[$key]) && $cache[$key] !== '' ? $cache[$key] : $default;
}

/* ------------------------------------------------------------------ */
/*  Output / request helpers                                          */
/* ------------------------------------------------------------------ */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        $out .= '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf']) && hash_equals(csrf_token(), (string) $_POST['csrf']);
}

function post(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

/** Rs. 70,000 — or a friendly fallback when no price is set. */
function money($amount, string $fallback = 'On consultation'): string
{
    if ($amount === null || $amount === '' || (int) $amount <= 0) {
        return $fallback;
    }
    return 'Rs. ' . number_format((int) $amount);
}

/** Resolve a stored image path (assets/... or uploads/...) for the current page. */
function img(?string $path, string $fallback = 'assets/img/model.jpg'): string
{
    return BASE . e($path ?: $fallback);
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item-' . time();
}

/** Digits-only international number for wa.me links. */
function whatsapp_link(string $text = ''): string
{
    $num = preg_replace('/\D/', '', setting('whatsapp', '923048398890'));
    return 'https://wa.me/' . $num . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/**
 * Save an uploaded image from $_FILES[$field] into /uploads.
 * Returns the stored relative path, null when no file was sent,
 * or throws RuntimeException with a user-friendly message.
 */
function upload_image(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed (error code ' . $f['error'] . ').');
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must be 5 MB or smaller.');
    }
    $info = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if (!$info || !isset($allowed[$info[2]])) {
        throw new RuntimeException('Please upload a JPG, PNG, WEBP or GIF image.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . $name)) {
        throw new RuntimeException('Could not save the uploaded image. Check that /uploads is writable.');
    }
    return 'uploads/' . $name;
}

/** Remove a previously uploaded file (never touches bundled assets/). */
function delete_upload(?string $path): void
{
    if ($path && str_starts_with($path, 'uploads/')) {
        $full = __DIR__ . '/' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

/* ------------------------------------------------------------------ */
/*  Booking time slots                                                */
/* ------------------------------------------------------------------ */

/** All slot start times ("HH:MM") between opening and closing time. */
function all_slots(): array
{
    $open  = strtotime('2000-01-01 ' . setting('open_time', '11:00'));
    $close = strtotime('2000-01-01 ' . setting('close_time', '20:00'));
    $step  = max(15, (int) setting('slot_minutes', '60')) * 60;
    $slots = [];
    for ($t = $open; $t < $close; $t += $step) {
        $slots[] = date('H:i', $t);
    }
    return $slots;
}

/** Slots still bookable on a date (respects capacity, closed day and past times). */
function available_slots(string $date): array
{
    $ts = strtotime($date);
    if (!$ts || $date < date('Y-m-d')) {
        return [];
    }
    $closedDay = setting('closed_day', '');
    if ($closedDay !== '' && date('l', $ts) === $closedDay) {
        return [];
    }
    $cap = max(1, (int) setting('slot_capacity', '2'));
    $taken = [];
    $rows = q("SELECT TIME_FORMAT(booking_time, '%H:%i') t, COUNT(*) c FROM bookings
               WHERE booking_date = ? AND status IN ('pending','confirmed') GROUP BY booking_time", [$date])->fetchAll();
    foreach ($rows as $r) {
        $taken[$r['t']] = (int) $r['c'];
    }
    $out = [];
    foreach (all_slots() as $s) {
        if ($date === date('Y-m-d') && $s <= date('H:i', time() + 3600)) {
            continue;   // need at least an hour's notice for same-day bookings
        }
        if (($taken[$s] ?? 0) < $cap) {
            $out[] = $s;
        }
    }
    return $out;
}

function slot_label(string $hhmm): string
{
    return date('g:i A', strtotime('2000-01-01 ' . $hhmm));
}
