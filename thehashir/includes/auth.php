<?php
/**
 * Authentication, authorization, CSRF, login throttling and activity logging
 * for the Super Admin panel (th_admin_table) and the mosque/organization portal (th_venue accounts).
 *
 * Existing tables are used as they are:
 *   th_admin_table.admin_password   varchar(100)  -> holds a bcrypt hash (60 chars) once migrated
 *   th_venue.password               varchar(200)  -> same
 *   th_venue.reset_token / token_expiry           -> portal password reset
 *   th_display_audit_logs                         -> activity log (actor_type admin|venue)
 */

// ---------------------------------------------------------------------------
// Passwords
// ---------------------------------------------------------------------------
function is_password_hash($stored)
{
    $info = password_get_info((string)$stored);
    return !empty($info['algo']);
}

/**
 * Compare a submitted password with the stored value.
 * Returns array(bool ok, bool legacy). "legacy" = stored value is not a password_hash() hash.
 * Legacy formats accepted for the one-time migration: plain text, or an unsalted MD5 hex digest.
 */
function password_check($input, $stored)
{
    $input = (string)$input;
    $stored = (string)$stored;
    if ($input === '' || $stored === '') {
        return array(false, false);
    }
    if (is_password_hash($stored)) {
        return array(password_verify($input, $stored), false);
    }
    if (preg_match('/^[a-f0-9]{32}$/i', $stored) && hash_equals(strtolower($stored), md5($input))) {
        return array(true, true);
    }
    return array(hash_equals($stored, $input), true);
}

function password_make($plain)
{
    return password_hash((string)$plain, PASSWORD_DEFAULT);
}

/** Returns '' when acceptable, otherwise the reason. */
function password_problem($plain, $confirm = null)
{
    $plain = (string)$plain;
    if ($confirm !== null && $plain !== (string)$confirm) {
        return 'The two passwords do not match.';
    }
    if (strlen($plain) < 8) {
        return 'Use at least 8 characters.';
    }
    if (strlen($plain) > 72) {
        return 'Use 72 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $plain) || !preg_match('/[0-9]/', $plain)) {
        return 'Use at least one letter and one number.';
    }
    return '';
}

// ---------------------------------------------------------------------------
// Login throttling (per account and per IP), stored in STORAGE_DIR/throttle
// ---------------------------------------------------------------------------
function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : '';
}

function throttle_file($key)
{
    return 'throttle/' . sha1($key) . '.json';
}

/** Seconds until the key may try again (0 = allowed). */
function throttle_wait($key)
{
    $t = storage_read(throttle_file($key), array());
    if (!empty($t['locked_until']) && $t['locked_until'] > time()) {
        return $t['locked_until'] - time();
    }
    return 0;
}

function throttle_fail($key)
{
    $f = throttle_file($key);
    $t = storage_read($f, array());
    $window = LOGIN_LOCK_MINUTES * 60;
    if (empty($t['first']) || $t['first'] < time() - $window) {
        $t = array('first' => time(), 'count' => 0);
    }
    $t['count']++;
    if ($t['count'] >= LOGIN_MAX_ATTEMPTS) {
        $t['locked_until'] = time() + $window;
    }
    try {
        storage_write($f, $t);
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

function throttle_clear($key)
{
    storage_delete(throttle_file($key));
}

/** Check both the account and the IP. Returns '' or a message. */
function login_blocked_message($scope, $user)
{
    $wait = max(throttle_wait($scope . '|u|' . strtolower($user)), throttle_wait($scope . '|ip|' . client_ip()));
    if ($wait > 0) {
        return 'Too many failed attempts. Please try again in ' . max(1, (int)ceil($wait / 60)) . ' minute(s).';
    }
    return '';
}

function login_failed($scope, $user)
{
    throttle_fail($scope . '|u|' . strtolower($user));
    throttle_fail($scope . '|ip|' . client_ip());
}

function login_succeeded($scope, $user)
{
    throttle_clear($scope . '|u|' . strtolower($user));
}

// ---------------------------------------------------------------------------
// Sessions
// ---------------------------------------------------------------------------
function session_touch_or_expire($key)
{
    if (empty($_SESSION[$key])) {
        return false;
    }
    $last = isset($_SESSION[$key]['last']) ? (int)$_SESSION[$key]['last'] : 0;
    if ($last && $last < time() - SESSION_IDLE_MINUTES * 60) {
        unset($_SESSION[$key]);
        return false;
    }
    $_SESSION[$key]['last'] = time();
    return true;
}

function current_admin()
{
    return session_touch_or_expire('admin') ? $_SESSION['admin'] : null;
}

function current_venue()
{
    return session_touch_or_expire('venue') ? $_SESSION['venue'] : null;
}

function current_venue_id()
{
    $v = current_venue();
    return $v ? (int)$v['id'] : 0;
}

/** Admin roles are kept in storage (th_admin_table has no role column). Default: super. */
function admin_roles()
{
    return storage_read('admin_roles.json', array());
}

function admin_role($adminId)
{
    $roles = admin_roles();
    return isset($roles[(string)$adminId]) ? $roles[(string)$adminId] : 'super';
}

/**
 * Permissions per role.
 *  super  : everything
 *  editor : content (venues, details, blogs, pages, prayer, approvals) — not users, settings or logs
 */
function admin_can($perm)
{
    $a = current_admin();
    if (!$a) {
        return false;
    }
    if ($a['role'] === 'super') {
        return true;
    }
    $editor = array('dashboard', 'venues', 'details', 'blogs', 'pages', 'prayer', 'approvals', 'locations', 'announcements', 'account');
    return in_array($perm, $editor, true);
}

function require_admin($perm = 'dashboard')
{
    $a = current_admin();
    if (!$a) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'];
        redirect(ADMIN_PATH . '/login', 302);
    }
    if (!admin_can($perm)) {
        http_response_code(403);
        bo_start('admin', 'Not allowed');
        echo '<div class="alert alert-warning">Your role does not allow access to this section.</div>';
        bo_end();
        exit;
    }
    return $a;
}

function require_venue()
{
    $v = current_venue();
    if (!$v) {
        $_SESSION['after_login_portal'] = $_SERVER['REQUEST_URI'];
        redirect(PORTAL_PATH . '/login', 302);
    }
    return $v;
}

function admin_login($user, $pass)
{
    $user = trim((string)$user);
    $block = login_blocked_message('admin', $user);
    if ($block !== '') {
        return $block;
    }
    $row = db_one('SELECT admin_id, admin_name, user_name, admin_email, admin_password, admin_status FROM th_admin_table
        WHERE (user_name = ? OR admin_email = ?) LIMIT 1', 'ss', array($user, $user));
    $check = $row ? password_check($pass, $row['admin_password']) : array(false, false);
    if (!$row || !$check[0] || (int)$row['admin_status'] !== 1) {
        login_failed('admin', $user);
        if ($row && $check[0]) {
            return 'This account is deactivated.';
        }
        return 'The username or password is incorrect.';
    }
    login_succeeded('admin', $user);
    if ($check[1] && PASSWORD_MIGRATE_ON_LOGIN) {
        db_exec('UPDATE th_admin_table SET admin_password = ? WHERE admin_id = ?', 'si', array(password_make($pass), (int)$row['admin_id']));
        log_activity('admin', (int)$row['admin_id'], 'auth.password_migrated', 'admin', (int)$row['admin_id']);
    } elseif (!$check[1] && password_needs_rehash($row['admin_password'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE th_admin_table SET admin_password = ? WHERE admin_id = ?', 'si', array(password_make($pass), (int)$row['admin_id']));
    }
    session_regenerate_id(true);
    unset($_SESSION['venue']);
    $_SESSION['admin'] = array(
        'id' => (int)$row['admin_id'],
        'name' => $row['admin_name'] !== '' ? $row['admin_name'] : $row['user_name'],
        'role' => admin_role((int)$row['admin_id']),
        'last' => time(),
    );
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    log_activity('admin', (int)$row['admin_id'], 'auth.login', 'admin', (int)$row['admin_id']);
    return '';
}

function venue_login($user, $pass)
{
    $user = trim((string)$user);
    $block = login_blocked_message('venue', $user);
    if ($block !== '') {
        return $block;
    }
    $rows = db_all('SELECT venue_id, venue_title, role_id, username, password FROM th_venue WHERE username = ? ORDER BY venue_id LIMIT 5', 's', array($user));
    $row = null;
    $check = array(false, false);
    foreach ($rows as $r) {
        $c = password_check($pass, $r['password']);
        if ($c[0]) {
            $row = $r;
            $check = $c;
            break;
        }
    }
    if (!$row) {
        login_failed('venue', $user);
        return 'The username or password is incorrect.';
    }
    login_succeeded('venue', $user);
    if ($check[1] && PASSWORD_MIGRATE_ON_LOGIN) {
        db_exec('UPDATE th_venue SET password = ? WHERE venue_id = ?', 'si', array(password_make($pass), (int)$row['venue_id']));
        log_activity('venue', (int)$row['venue_id'], 'auth.password_migrated', 'venue', (int)$row['venue_id']);
    }
    session_regenerate_id(true);
    unset($_SESSION['admin']);
    $_SESSION['venue'] = array(
        'id' => (int)$row['venue_id'],
        'name' => trim((string)$row['venue_title']),
        'role_id' => (int)$row['role_id'],
        'last' => time(),
    );
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    log_activity('venue', (int)$row['venue_id'], 'auth.login', 'venue', (int)$row['venue_id']);
    return '';
}

function logout_all()
{
    $a = isset($_SESSION['admin']) ? $_SESSION['admin'] : null;
    $v = isset($_SESSION['venue']) ? $_SESSION['venue'] : null;
    if ($a) {
        log_activity('admin', $a['id'], 'auth.logout', 'admin', $a['id']);
    }
    if ($v) {
        log_activity('venue', $v['id'], 'auth.logout', 'venue', $v['id']);
    }
    $_SESSION = array();
    session_regenerate_id(true);
}

// ---------------------------------------------------------------------------
// CSRF for back-office forms
// ---------------------------------------------------------------------------
function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Stop POST requests without a valid token. */
function require_post_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed');
    }
    $token = isset($_POST['csrf']) ? $_POST['csrf'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    if (!csrf_check($token)) {
        if (is_ajax()) {
            json_out(array('ok' => false, 'message' => 'Your session expired. Reload the page and try again.'), 400);
        }
        flash('danger', 'Your session expired. Please try again.');
        redirect_back();
    }
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function is_ajax()
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false && $_SERVER['REQUEST_METHOD'] === 'POST');
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function flash($type, $msg)
{
    $_SESSION['flash'][] = array($type, $msg);
}

function flashes()
{
    $f = isset($_SESSION['flash']) ? $_SESSION['flash'] : array();
    unset($_SESSION['flash']);
    return $f;
}

function redirect_back($fallback = '/')
{
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref, true, 303);
        exit;
    }
    redirect($fallback, 303);
}

// ---------------------------------------------------------------------------
// Activity log -> th_display_audit_logs (existing table, actor_type admin|venue)
// ---------------------------------------------------------------------------
function log_activity($actorType, $actorId, $action, $entityType, $entityId = null, $old = null, $new = null)
{
    $strip = function ($d) {
        if (!is_array($d)) {
            return $d;
        }
        foreach (array('password', 'admin_password', 'reset_token', 'csrf', 'pass', 'password_confirm') as $k) {
            unset($d[$k]);
        }
        return $d;
    };
    try {
        db_exec('INSERT INTO th_display_audit_logs (actor_type, admin_id, venue_id, action, entity_type, entity_id, old_data, new_data, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 'siissssss', array(
            $actorType === 'admin' ? 'admin' : 'venue',
            $actorType === 'admin' ? (int)$actorId : null,
            $actorType === 'admin' ? null : (int)$actorId,
            substr($action, 0, 100),
            substr($entityType, 0, 100),
            $entityId === null ? null : (int)$entityId,
            $old === null ? null : json_encode($strip($old), JSON_UNESCAPED_UNICODE),
            $new === null ? null : json_encode($strip($new), JSON_UNESCAPED_UNICODE),
            client_ip(),
        ));
    } catch (Exception $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}

/** Log as whoever is signed in. */
function log_me($action, $entityType, $entityId = null, $old = null, $new = null)
{
    $a = current_admin();
    if ($a) {
        log_activity('admin', $a['id'], $action, $entityType, $entityId, $old, $new);
        return;
    }
    $v = current_venue();
    if ($v) {
        log_activity('venue', $v['id'], $action, $entityType, $entityId, $old, $new);
    }
}
