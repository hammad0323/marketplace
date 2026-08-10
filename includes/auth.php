<?php
/**
 * Authentication, role guards and session helpers.
 * Session keys set on login: user_id, user_role, user_name, user_email.
 */
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

function current_user_role()
{
    return $_SESSION['user_role'] ?? null;
}

function current_user($conn)
{
    if (!is_logged_in()) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $user = db_select_one(
            $conn,
            'SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active"',
            [(int) current_user_id()]
        );
    }
    return $user;
}

function require_login($role = null)
{
    if (!is_logged_in()) {
        $target = $role === 'admin' ? '/admin/login.php' : ($role === 'provider' ? '/provider/login.php' : '/customer/login.php');
        redirect($target . '?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    if ($role !== null && current_user_role() !== $role) {
        http_response_code(403);
        exit('You are not authorized to view this page.');
    }
}

// ---------------------------------------------------------------------
// Login rate limiting
// ---------------------------------------------------------------------

function login_attempts_recent($conn, $email, $ip)
{
    return db_count(
        $conn,
        'SELECT COUNT(*) AS c FROM login_attempts WHERE email = ? AND ip_address = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ' . LOGIN_LOCKOUT_MINUTES . ' MINUTE)',
        [$email, $ip]
    );
}

function record_login_attempt($conn, $email, $ip, $success)
{
    db_execute($conn, 'INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)', [$email, $ip, $success ? 1 : 0]);
}

// ---------------------------------------------------------------------
// Login / logout
// ---------------------------------------------------------------------

/**
 * @return array{ok:bool, error?:string, user?:array}
 */
function attempt_login($conn, $email, $password, $roleSlug)
{
    $email = strtolower(clean_input($email));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    if (login_attempts_recent($conn, $email, $ip) >= MAX_LOGIN_ATTEMPTS) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.'];
    }

    $user = db_select_one(
        $conn,
        'SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND r.slug = ?',
        [$email, $roleSlug]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($conn, $email, $ip, false);
        return ['ok' => false, 'error' => 'Incorrect email or password.'];
    }
    if ($user['status'] !== 'active') {
        record_login_attempt($conn, $email, $ip, false);
        return ['ok' => false, 'error' => 'This account is not active. Contact support if you believe this is a mistake.'];
    }

    record_login_attempt($conn, $email, $ip, true);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role_slug'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    db_execute($conn, 'UPDATE users SET last_login_at = NOW() WHERE id = ?', [(int) $user['id']]);
    log_activity($conn, (int) $user['id'], 'login', 'Logged in as ' . $roleSlug);

    return ['ok' => true, 'user' => $user];
}

function logout_user($conn)
{
    if (is_logged_in()) {
        log_activity($conn, (int) current_user_id(), 'logout', null);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------
// Registration
// ---------------------------------------------------------------------

/**
 * @return array{ok:bool, error?:string, user_id?:int}
 */
function register_customer($conn, $name, $email, $phone, $password)
{
    $email = strtolower(clean_input($email));
    if (db_select_one($conn, 'SELECT id FROM users WHERE email = ?', [$email])) {
        return ['ok' => false, 'error' => 'An account with that email already exists.'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $userId = db_insert_get_id(
        $conn,
        'INSERT INTO users (role_id, name, email, phone, password_hash, status) VALUES (3, ?, ?, ?, ?, "active")',
        [clean_input($name), $email, clean_input($phone), $hash]
    );
    if (!$userId) {
        return ['ok' => false, 'error' => 'Could not create your account. Please try again.'];
    }
    log_activity($conn, $userId, 'register', 'Customer account created');
    return ['ok' => true, 'user_id' => $userId];
}

/**
 * @return array{ok:bool, error?:string, user_id?:int, provider_id?:int}
 */
function register_provider($conn, $data)
{
    $email = strtolower(clean_input($data['email']));
    if (db_select_one($conn, 'SELECT id FROM users WHERE email = ?', [$email])) {
        return ['ok' => false, 'error' => 'An account with that email already exists.'];
    }

    mysqli_begin_transaction($conn);
    try {
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $userId = db_insert_get_id(
            $conn,
            'INSERT INTO users (role_id, name, email, phone, password_hash, status) VALUES (2, ?, ?, ?, ?, "active")',
            [clean_input($data['name']), $email, clean_input($data['phone']), $hash]
        );
        if (!$userId) {
            throw new Exception('user insert failed');
        }

        $slug = unique_slug($conn, 'providers', $data['business_name']);
        $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
        $cityId = !empty($data['city_id']) ? (int) $data['city_id'] : null;

        $providerId = db_insert_get_id(
            $conn,
            'INSERT INTO providers (user_id, business_name, slug, category_id, city_id, address, description, website, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")',
            [
                $userId,
                clean_input($data['business_name']),
                $slug,
                $categoryId,
                $cityId,
                clean_input($data['address'] ?? ''),
                clean_input($data['description'] ?? ''),
                clean_input($data['website'] ?? ''),
            ]
        );
        if (!$providerId) {
            throw new Exception('provider insert failed');
        }

        mysqli_commit($conn);
        log_activity($conn, $userId, 'register', 'Provider account created, pending approval');
        return ['ok' => true, 'user_id' => $userId, 'provider_id' => $providerId];
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log('register_provider failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not create your provider account. Please try again.'];
    }
}

// ---------------------------------------------------------------------
// Password reset
// ---------------------------------------------------------------------

function create_password_reset($conn, $email)
{
    $user = db_select_one($conn, 'SELECT id FROM users WHERE email = ?', [strtolower(clean_input($email))]);
    if (!$user) {
        return null;
    }
    $token = bin2hex(random_bytes(32));
    db_execute(
        $conn,
        'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
        [(int) $user['id'], $token]
    );
    return $token;
}

function verify_password_reset_token($conn, $token)
{
    return db_select_one(
        $conn,
        'SELECT pr.*, u.email FROM password_resets pr JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()',
        [$token]
    );
}

function consume_password_reset($conn, $token, $newPassword)
{
    $reset = verify_password_reset_token($conn, $token);
    if (!$reset) {
        return false;
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db_execute($conn, 'UPDATE users SET password_hash = ? WHERE id = ?', [$hash, (int) $reset['user_id']]);
    db_execute($conn, 'UPDATE password_resets SET used = 1 WHERE id = ?', [(int) $reset['id']]);
    return true;
}
