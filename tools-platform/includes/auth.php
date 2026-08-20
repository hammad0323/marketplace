<?php
/**
 * auth.php — admin authentication. Session-based; no JWT, no OAuth.
 * Public users never authenticate (favorites/history use localStorage
 * by default) — this file is solely the Admin Panel's guard.
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

function tp_admin_attempt_login(string $email, string $password): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!tp_rate_limit('login:' . $ip, 8, 300)) {
        return ['success' => false, 'error' => 'Too many attempts. Please wait a few minutes and try again.'];
    }

    $admin = tp_query_one('SELECT * FROM admins WHERE email = ? LIMIT 1', 's', [$email]);
    if (!$admin || $admin['status'] !== 'active' || !password_verify($password, $admin['password_hash'])) {
        tp_log_activity(null, 'login_failed', 'admin', null, $email);
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_role'] = $admin['role'];

    tp_execute('UPDATE admins SET last_login_at = NOW() WHERE id = ?', 'i', [$admin['id']]);
    tp_log_activity((int) $admin['id'], 'login_success', 'admin', (int) $admin['id'], null);

    return ['success' => true, 'error' => null];
}

function tp_admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
    session_regenerate_id(true);
}

function tp_current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $admin = null;
    if ($admin === null) {
        $admin = tp_query_one('SELECT id, name, email, role FROM admins WHERE id = ?', 'i', [$_SESSION['admin_id']]);
    }
    return $admin;
}

function tp_is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/** Call at the very top of every admin/*.php page (after config.php). */
function require_admin(): void
{
    if (!tp_is_admin_logged_in()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . tp_url('admin/login.php?redirect=' . $redirect));
        exit;
    }
}

function require_super_admin(): void
{
    require_admin();
    $admin = tp_current_admin();
    if (!$admin || $admin['role'] !== 'super_admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function tp_admin_request_password_reset(string $email): void
{
    $admin = tp_query_one('SELECT id FROM admins WHERE email = ?', 's', [$email]);
    if (!$admin) {
        return; // do not reveal whether the email exists
    }
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);
    tp_execute(
        'UPDATE admins SET reset_token = ?, reset_expires = ? WHERE id = ?',
        'ssi',
        [$token, $expires, $admin['id']]
    );
    // Wire this token into your outgoing-mail mechanism of choice; for
    // now it's logged so the flow is testable without an SMTP server.
    error_log("Password reset requested for admin #{$admin['id']}: token={$token}");
}

function tp_admin_reset_password(string $token, string $newPassword): bool
{
    $admin = tp_query_one(
        'SELECT id FROM admins WHERE reset_token = ? AND reset_expires > NOW()',
        's',
        [$token]
    );
    if (!$admin) {
        return false;
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    tp_execute(
        'UPDATE admins SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?',
        'si',
        [$hash, $admin['id']]
    );
    return true;
}

function tp_log_activity(?int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    tp_execute(
        'INSERT INTO activity_logs (admin_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
        'ississ',
        [$adminId, $action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? null]
    );
}
