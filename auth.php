<?php
/**
 * auth.php — admin authentication, session, roles & permissions.
 * Requires functions.php (already loaded by config.php before this file).
 */

const WH_ROLE_PERMISSIONS = [
    'super_admin' => ['*'],
    'admin' => ['*'],
    'manager' => [
        'dashboard', 'bookings', 'booking-form', 'booking-view', 'calendar', 'date-search',
        'customers', 'customer-form', 'customer-view', 'payments', 'halls', 'hall-form', 'time-slots', 'event-types',
        'gallery', 'reports', 'chatbot', 'notifications',
    ],
    'staff' => [
        'dashboard', 'bookings', 'booking-form', 'booking-view', 'calendar', 'date-search',
        'customers', 'customer-form', 'customer-view', 'notifications',
    ],
];

function wh_admin_login($email, $password)
{
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Lockout: 5 failed attempts for this email within 15 minutes.
    $recentFails = wh_fetch_one(
        "SELECT COUNT(*) c FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL 15 MINUTE)",
        's',
        [$email]
    );
    if ((int) ($recentFails['c'] ?? 0) >= 5) {
        return ['ok' => false, 'error' => 'locked'];
    }

    $user = wh_fetch_one("SELECT * FROM admin_users WHERE email = ? AND status = 'active'", 's', [$email]);
    $valid = $user && password_verify($password, $user['password_hash']);

    wh_execute('INSERT INTO login_attempts (email, ip_address, success) VALUES (?,?,?)', 'ssi', [$email, $ip, $valid ? 1 : 0]);

    if (!$valid) {
        return ['ok' => false, 'error' => 'invalid'];
    }

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['admin_name'] = $user['name'];
    $_SESSION['admin_role'] = $user['role'];
    $_SESSION['admin_business_id'] = $user['business_id'] ? (int) $user['business_id'] : null;
    $_SESSION['must_change_password'] = (bool) $user['must_change_password'];
    $_SESSION['last_activity'] = time();

    wh_execute('UPDATE admin_users SET last_login = NOW() WHERE id = ?', 'i', [$user['id']]);

    return ['ok' => true, 'must_change_password' => (bool) $user['must_change_password']];
}

function wh_admin_logout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function wh_current_admin()
{
    static $admin = null;
    if ($admin !== null) {
        return $admin;
    }
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $admin = wh_fetch_one('SELECT * FROM admin_users WHERE id = ?', 'i', [$_SESSION['admin_id']]);
    return $admin;
}

function wh_is_logged_in()
{
    return !empty($_SESSION['admin_id']);
}

function wh_is_super_admin()
{
    return ($_SESSION['admin_role'] ?? '') === 'super_admin';
}

function wh_admin_role()
{
    return $_SESSION['admin_role'] ?? '';
}

function wh_require_admin()
{
    if (!wh_is_logged_in()) {
        wh_redirect(BASE_URL . '/admin/login.php');
    }
}

/**
 * Guard a page by key (matches the WH_ROLE_PERMISSIONS lists above).
 * Call at the top of every /admin/*.php page after wh_require_admin().
 */
function wh_require_page_access($pageKey)
{
    wh_require_admin();
    $role = wh_admin_role();
    $allowed = WH_ROLE_PERMISSIONS[$role] ?? [];
    if (in_array('*', $allowed, true) || in_array($pageKey, $allowed, true)) {
        return true;
    }
    http_response_code(403);
    require __DIR__ . '/admin/header.php';
    echo '<div class="admin-card"><h2>Access denied</h2><p>Your role (' . e($role) . ') does not have permission to view this page.</p></div>';
    require __DIR__ . '/admin/footer.php';
    exit;
}

/** Only super_admin may reach the platform-level (multi-business) screens. */
function wh_require_super_admin()
{
    wh_require_admin();
    if (!wh_is_super_admin()) {
        http_response_code(403);
        exit('Super Admin access only.');
    }
}
