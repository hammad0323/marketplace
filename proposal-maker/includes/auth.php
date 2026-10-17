<?php
/**
 * LOGIN — who is logged in, and page protection.
 *
 *   require_login()  put at the top of an admin page: sends visitors to login.php
 *   require_admin()  same, but only for users with the "admin" role
 */

function current_user()
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $user = db_row('SELECT id, name, email, role, status FROM users WHERE id = ? AND status = 1', [(int) $_SESSION['user_id']]);
        }
    }
    return $user;
}

function is_logged_in()
{
    return current_user() !== null;
}

function require_login()
{
    $u = current_user();
    if (!$u) {
        if (is_ajax()) {
            json_out(['ok' => false, 'message' => 'Please log in again.'], 401);
        }
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect('login.php');
    }
    return $u;
}

function require_admin()
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        if (is_ajax()) {
            json_out(['ok' => false, 'message' => 'Only administrators can do this.'], 403);
        }
        flash('danger', 'Only administrators can access that page.');
        redirect('index.php');
    }
    return $u;
}

function attempt_login($email, $password)
{
    // basic brute-force throttling per session
    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? [];
    // keep only the failed attempts of the last 10 minutes
    $recent = array();
    foreach ($_SESSION['login_attempts'] as $time) {
        if ($time > time() - 600) $recent[] = $time;
    }
    $_SESSION['login_attempts'] = $recent;
    if (count($_SESSION['login_attempts']) >= 8) {
        return false;
    }
    $u = db_row('SELECT * FROM users WHERE email = ? AND status = 1', [$email]);
    if ($u && password_verify($password, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $u['id'];
        $_SESSION['login_attempts'] = [];
        db_query('UPDATE users SET last_login = NOW() WHERE id = ?', [(int) $u['id']]);
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            db_query('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $u['id']]);
        }
        return true;
    }
    $_SESSION['login_attempts'][] = time();
    return false;
}

function logout()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
