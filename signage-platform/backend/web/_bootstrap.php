<?php
/**
 * Web app bootstrap (server-rendered core PHP pages).
 * Pages are thin PHP shells: PHP handles the session, access control and
 * layout; data comes from the same REST API the mobile app uses, so business
 * rules (limits, permissions, ownership, template access) live in one place.
 */
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/config/config.php';
require APP_ROOT . '/config/constants.php';
require APP_ROOT . '/config/database.php';
require APP_ROOT . '/includes/response.php';
require APP_ROOT . '/includes/functions.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/permissions.php';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_name('signage_web');
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** URL prefix of the backend folder (works at domain root or in a sub-folder). */
function web_base(): string
{
    if (APP_URL !== '') return rtrim(APP_URL, '/');
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $pos = strpos($script, '/web/');
    return $pos === false ? '' : substr($script, 0, $pos);
}
function web_url(string $path): string { return web_base() . '/web/' . ltrim($path, '/'); }

/** Current signed-in user (fresh from DB), or null. Validates the stored API token. */
function web_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $tok = $_SESSION['api_token'] ?? null;
    if (!$tok) return $u = null;
    $row = db_one('SELECT u.*, r.role_key FROM api_tokens t JOIN users u ON u.user_id = t.user_id JOIN roles r ON r.role_id = u.role_id
                    WHERE t.token_hash = ? AND t.revoked_at IS NULL AND t.expires_at > UTC_TIMESTAMP()', [sha256($tok)]);
    if (!$row || user_account_problem($row)) { $_SESSION = []; return $u = null; }
    return $u = $row;
}

function web_require_login(bool $admin = false): array
{
    $u = web_user();
    if (!$u) { header('Location: ' . web_url('login.php')); exit; }
    if ($admin && $u['role_key'] !== ROLE_SUPER_ADMIN) { header('Location: ' . web_url('dashboard.php')); exit; }
    return $u;
}

function web_csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = random_token(16);
    return $_SESSION['csrf'];
}
function web_check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Invalid form token. Reload the page and try again.'); }
}
