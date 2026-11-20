<?php
/**
 * Authentication.
 *  - Shop owners: bearer token (Flutter) or the same token in the httpOnly "ph_owner" cookie (owner web dashboard).
 *  - Super Admin panel: PHP session.
 *  - Customers: anonymous session token (X-Customer-Token header in the app, "ph_cs" cookie on the web). No account needed.
 * Identity (shop id, user id) always comes from the verified token — never from client input.
 */

// ---------------------------------------------------------------- tokens
function token_issue(string $userType, int $userId, ?int $shopId, ?string $device = null): string
{
    $token = random_token(32);
    db_insert('auth_tokens', ['token_hash' => hash('sha256', $token), 'user_type' => $userType, 'user_id' => $userId, 'shop_id' => $shopId,
        'device_name' => $device ? mb_substr($device, 0, 120) : null, 'ip_address' => client_ip(),
        'expires_at' => date('Y-m-d H:i:s', time() + TOKEN_TTL_DAYS * 86400)]);
    return $token;
}

function token_lookup(string $token, string $userType): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $t = db_row('SELECT * FROM auth_tokens WHERE token_hash = ? AND user_type = ? AND revoked_at IS NULL AND expires_at > NOW()', [hash('sha256', $token), $userType]);
    if ($t && (!$t['last_used_at'] || strtotime($t['last_used_at']) < time() - 300)) {
        db_exec('UPDATE auth_tokens SET last_used_at = NOW() WHERE id = ?', [(int) $t['id']]);
    }
    return $t;
}

function token_revoke(string $token): void
{
    db_exec('UPDATE auth_tokens SET revoked_at = NOW() WHERE token_hash = ?', [hash('sha256', $token)]);
}

function bearer_token(): ?string
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($h), $m) ? strtolower($m[1]) : null;
}

// ---------------------------------------------------------------- shop owners
/** Verifies owner credentials. Returns [user, shop] or an error string. */
function owner_login(string $username, string $password): array|string
{
    $key = 'owner-login:' . strtolower($username) . ':' . client_ip();
    if (!rate_limit($key, 8, 900)) {
        return 'Too many attempts. Please wait 15 minutes and try again.';
    }
    $u = db_row('SELECT * FROM shop_users WHERE (username = ? OR (email = ? AND email <> \'\')) AND deleted_at IS NULL', [$username, $username]);
    if (!$u || !password_verify($password, $u['password_hash'])) {
        return 'Incorrect username or password.';
    }
    if (!(int) $u['is_active']) {
        return 'This account is disabled. Please contact support.';
    }
    $shop = db_row('SELECT * FROM shops WHERE id = ? AND deleted_at IS NULL', [(int) $u['shop_id']]);
    if (!$shop || $shop['status'] === 'deactivated') {
        return 'This shop account has been deactivated. Please contact support.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE shop_users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $u['id']]);
    }
    rate_limit_clear($key);
    db_exec('UPDATE shop_users SET last_login_at = NOW() WHERE id = ?', [(int) $u['id']]);
    activity('owner', (int) $u['id'], 'login', (int) $shop['id']);
    return [$u, $shop];
}

/** The signed-in shop user for this request (API bearer token or owner web cookie), or null. */
function current_owner(): ?array
{
    static $ctx = false;
    if ($ctx !== false) {
        return $ctx;
    }
    $ctx = null;
    $token = bearer_token() ?? (($_COOKIE['ph_owner'] ?? '') ?: null);
    if (!$token) {
        return null;
    }
    $t = token_lookup($token, 'owner');
    if (!$t) {
        return null;
    }
    $u = db_row('SELECT * FROM shop_users WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [(int) $t['user_id']]);
    $shop = $u ? db_row('SELECT * FROM shops WHERE id = ? AND deleted_at IS NULL AND status <> \'deactivated\'', [(int) $u['shop_id']]) : null;
    if (!$u || !$shop || (int) $shop['id'] !== (int) $t['shop_id']) {
        return null;
    }
    unset($u['password_hash'], $u['reset_token_hash']);
    return $ctx = ['user' => $u, 'shop' => $shop, 'token' => $token, 'via_cookie' => !bearer_token()];
}

function owner_reset_request(string $login): void
{
    $u = db_row('SELECT * FROM shop_users WHERE (username = ? OR email = ?) AND is_active = 1 AND deleted_at IS NULL', [$login, $login]);
    if (!$u || !$u['email']) {
        return; // same response either way
    }
    $token = random_token(24);
    db_exec('UPDATE shop_users SET reset_token_hash = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?', [hash('sha256', $token), (int) $u['id']]);
    send_mail($u['email'], platform_name() . ' password reset', "Hello {$u['name']},\n\nReset your password using this link (valid for 1 hour):\n" . url('owner/reset.php?token=' . $token) . "\n\nIf you did not ask for this, ignore this email.");
}

function owner_reset_apply(string $token, string $password): ?string
{
    if ($err = password_ok($password)) {
        return $err;
    }
    $u = db_row('SELECT id, shop_id FROM shop_users WHERE reset_token_hash = ? AND reset_expires_at > NOW() AND deleted_at IS NULL', [hash('sha256', $token)]);
    if (!$u) {
        return 'This reset link is invalid or has expired.';
    }
    db_exec('UPDATE shop_users SET password_hash = ?, reset_token_hash = NULL, reset_expires_at = NULL, must_change_password = 0 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $u['id']]);
    db_exec("UPDATE auth_tokens SET revoked_at = NOW() WHERE user_type = 'owner' AND user_id = ? AND revoked_at IS NULL", [(int) $u['id']]);
    activity('owner', (int) $u['id'], 'password_reset', (int) $u['shop_id']);
    return null;
}

function owner_change_password(array $user, string $current, string $new, ?string $keepToken = null): ?string
{
    $hash = (string) db_val('SELECT password_hash FROM shop_users WHERE id = ?', [(int) $user['id']]);
    if (!password_verify($current, $hash)) {
        return 'Your current password is incorrect.';
    }
    if ($err = password_ok($new)) {
        return $err;
    }
    db_exec('UPDATE shop_users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
    // sign out every other device
    db_exec("UPDATE auth_tokens SET revoked_at = NOW() WHERE user_type = 'owner' AND user_id = ? AND revoked_at IS NULL AND token_hash <> ?", [(int) $user['id'], $keepToken ? hash('sha256', $keepToken) : '']);
    activity('owner', (int) $user['id'], 'password_changed', (int) $user['shop_id']);
    return null;
}

// ---------------------------------------------------------------- Super Admin (web session)
function admin_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('ph_admin');
        session_set_cookie_params(['lifetime' => 0, 'path' => app_base_path() . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function current_admin(): ?array
{
    static $a = false;
    if ($a !== false) {
        return $a;
    }
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    $a = $id ? db_row('SELECT id, name, email, role, must_change_password FROM admins WHERE id = ? AND is_active = 1', [$id]) : null;
    if ($a && ($_SESSION['admin_fp'] ?? '') !== substr(sign(($_SERVER['HTTP_USER_AGENT'] ?? '') . $id), 0, 32)) {
        $a = null;
    }
    return $a;
}

function admin_login(string $email, string $password): ?string
{
    $key = 'admin-login:' . strtolower($email) . ':' . client_ip();
    if (!rate_limit($key, 6, 900)) {
        return 'Too many attempts. Please wait 15 minutes.';
    }
    $a = db_row('SELECT * FROM admins WHERE email = ? AND is_active = 1', [$email]);
    if (!$a || !password_verify($password, $a['password_hash'])) {
        return 'Incorrect email or password.';
    }
    rate_limit_clear($key);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $a['id'];
    $_SESSION['admin_fp'] = substr(sign(($_SERVER['HTTP_USER_AGENT'] ?? '') . $a['id']), 0, 32);
    db_exec('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), (int) $a['id']]);
    activity('admin', (int) $a['id'], 'login');
    return null;
}

// ---------------------------------------------------------------- customers (anonymous sessions)
function customer_session(bool $create = true, string $platform = 'web'): ?array
{
    static $s = false;
    if ($s !== false && ($s || !$create)) {
        return $s;
    }
    $header = $_SERVER['HTTP_X_CUSTOMER_TOKEN'] ?? '';
    $token = preg_match('/^[a-f0-9]{64}$/', $header) ? $header : (($_COOKIE['ph_cs'] ?? '') ?: null);
    $row = null;
    if ($token && preg_match('/^[a-f0-9]{64}$/', $token)) {
        $row = db_row('SELECT * FROM customer_sessions WHERE token_hash = ? AND expires_at > NOW()', [hash('sha256', $token)]);
        if ($row && (!$row['last_seen_at'] || strtotime($row['last_seen_at']) < time() - 600)) {
            db_exec('UPDATE customer_sessions SET last_seen_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?', [CUSTOMER_SESSION_DAYS, (int) $row['id']]);
        }
    }
    if (!$row && $create) {
        $token = random_token(32);
        $id = db_insert('customer_sessions', ['token_hash' => hash('sha256', $token), 'platform' => $platform, 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'ip_address' => client_ip(), 'expires_at' => date('Y-m-d H:i:s', time() + CUSTOMER_SESSION_DAYS * 86400), 'last_seen_at' => now()]);
        $row = db_row('SELECT * FROM customer_sessions WHERE id = ?', [$id]);
        if ($platform === 'web' && !$header) {
            set_cookie('ph_cs', $token, CUSTOMER_SESSION_DAYS * 86400);
        }
        $row['_new_token'] = $token;
    }
    return $s = $row;
}
