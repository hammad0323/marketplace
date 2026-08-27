<?php
/**
 * Authentication Engine
 * Session shape:
 *  $_SESSION['auth_type'] = 'admin' | 'user'
 *  $_SESSION['role']      = 'super_admin' | 'manager' | 'employee'
 *  $_SESSION['user_id']
 *  $_SESSION['company_id'] (null for super_admin)
 *  $_SESSION['name'], $_SESSION['email']
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['role']) && !empty($_SESSION['user_id']);
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_company_id(): ?int
{
    return isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : null;
}

function current_user_name(): string
{
    return $_SESSION['name'] ?? '';
}

function login_super_admin(string $email, string $password): array
{
    if (!rate_limit('login_admin_' . $email, 'login', 8, 300)) {
        return ['success' => false, 'message' => 'Too many attempts. Please try again later.'];
    }
    $admin = db_fetch_one("SELECT * FROM admins WHERE email = ? AND status = 'active' LIMIT 1", 's', [$email]);
    if (!$admin || !verify_password($password, $admin['password'])) {
        log_activity(null, null, 'login_failed', 'admin', null, "Failed login attempt: $email");
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    session_regenerate_id(true);
    $_SESSION['auth_type'] = 'admin';
    $_SESSION['role'] = 'super_admin';
    $_SESSION['user_id'] = (int)$admin['id'];
    $_SESSION['company_id'] = null;
    $_SESSION['name'] = $admin['name'];
    $_SESSION['email'] = $admin['email'];
    db_execute("UPDATE admins SET last_login = NOW() WHERE id = ?", 'i', [$admin['id']]);
    log_activity(null, (int)$admin['id'], 'login', 'admin', (int)$admin['id'], 'Super admin logged in');
    return ['success' => true];
}

function login_company_user(string $email, string $password): array
{
    if (!rate_limit('login_user_' . $email, 'login', 8, 300)) {
        return ['success' => false, 'message' => 'Too many attempts. Please try again later.'];
    }
    $user = db_fetch_one("SELECT u.*, c.status AS company_status, c.expiry_date FROM users u
        JOIN companies c ON c.id = u.company_id WHERE u.email = ? LIMIT 1", 's', [$email]);
    if (!$user || !verify_password($password, $user['password'])) {
        log_activity(null, null, 'login_failed', 'user', null, "Failed login attempt: $email");
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Your account has been disabled. Contact your manager.'];
    }
    if ($user['company_status'] !== 'active') {
        return ['success' => false, 'message' => 'Your company account is currently inactive.'];
    }
    if (!empty($user['expiry_date']) && strtotime($user['expiry_date']) < time()) {
        return ['success' => false, 'message' => 'Your company subscription has expired.'];
    }
    session_regenerate_id(true);
    $_SESSION['auth_type'] = 'user';
    $_SESSION['role'] = $user['role'];
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['company_id'] = (int)$user['company_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['department_id'] = $user['department_id'];
    db_execute("UPDATE users SET last_login = NOW() WHERE id = ?", 'i', [$user['id']]);
    log_activity((int)$user['company_id'], (int)$user['id'], 'login', 'user', (int)$user['id'], ucfirst($user['role']) . ' logged in');
    return ['success' => true, 'role' => $user['role']];
}

function do_logout(): void
{
    if (is_logged_in()) {
        log_activity(current_company_id(), current_user_id(), 'logout', $_SESSION['auth_type'] ?? 'user', current_user_id(), 'User logged out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function log_activity(?int $companyId, ?int $userId, string $action, string $module, ?int $recordId, string $description = ''): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    db_execute(
        "INSERT INTO activity_logs (company_id, user_id, action, module, record_id, description, ip_address, user_agent, created_at)
         VALUES (?,?,?,?,?,?,?,?,NOW())",
        'iississs',
        [$companyId, $userId, $action, $module, $recordId, $description, $ip, $ua]
    );
}
