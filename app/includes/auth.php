<?php
/**
 * Customer and administrator authentication, permissions and audit logging.
 * Customers and admins use separate session keys and separate tables.
 */

// ---- Customers --------------------------------------------------------------

function current_customer(): ?array
{
    static $cache = [];
    $id = (int) ($_SESSION['customer_id'] ?? 0);
    if (!$id) {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = db_one("SELECT * FROM customers WHERE id = ? AND status = 'active'", [$id]);
        if (!$cache[$id]) {
            unset($_SESSION['customer_id']);
        }
    }
    return $cache[$id];
}

function customer_id(): ?int
{
    $c = current_customer();
    return $c ? (int) $c['id'] : null;
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? path_url('account');
        flash('info', 'Please sign in to continue.');
        redirect(path_url('account/login'));
    }
    return $c;
}

/** Attempt a customer login. Returns error message or null on success. */
function customer_login(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    $ident = $email . '|' . client_ip();
    if (!rate_limit('customer_login', $ident, 6, 900)) {
        return 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    }
    $c = db_one('SELECT * FROM customers WHERE email = ?', [$email]);
    if (!$c || !password_verify($password, $c['password_hash'])) {
        return 'The email or password you entered is incorrect.';
    }
    if ($c['status'] !== 'active') {
        return 'This account has been disabled. Please contact support.';
    }
    rate_limit_clear('customer_login', $ident);
    customer_start_session((int) $c['id']);
    if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $c['id']]);
    }
    return null;
}

function customer_start_session(int $customerId): void
{
    $guestCartToken = $_SESSION['cart_token'] ?? null;
    $guestWishToken = $_SESSION['wishlist_token'] ?? null;
    session_regenerate_id(true);
    $_SESSION['customer_id'] = $customerId;
    db_exec('UPDATE customers SET last_login_at = NOW() WHERE id = ?', [$customerId]);
    cart_merge_guest($guestCartToken, $customerId);
    wishlist_merge_guest($guestWishToken, $customerId);
}

function customer_logout(): void
{
    unset($_SESSION['customer_id'], $_SESSION['cart_token'], $_SESSION['wishlist_token']);
    session_regenerate_id(true);
}

/** Validate & create a customer. Returns [id|null, errors[]]. */
function customer_register(array $d): array
{
    $errors = [];
    $first = trim($d['first_name'] ?? '');
    $last = trim($d['last_name'] ?? '');
    $email = mb_strtolower(trim($d['email'] ?? ''));
    $phone = trim($d['phone'] ?? '');
    $pass = (string) ($d['password'] ?? '');

    if ($first === '' || mb_strlen($first) > 80) {
        $errors['first_name'] = 'Please enter your first name.';
    }
    if (mb_strlen($last) > 80) {
        $errors['last_name'] = 'Last name is too long.';
    }
    if (!valid_email($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (db_val('SELECT COUNT(*) FROM customers WHERE email = ?', [$email])) {
        $errors['email'] = 'An account with this email already exists. Please sign in instead.';
    }
    if ($phone !== '' && !valid_phone($phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    if ($err = password_policy_error($pass)) {
        $errors['password'] = $err;
    } elseif (isset($d['password_confirm']) && $d['password_confirm'] !== $pass) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if ($errors) {
        return [null, $errors];
    }
    $id = db_insert('customers', [
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'phone' => $phone ?: null,
        'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
    ]);
    return [$id, []];
}

function password_policy_error(string $pass): ?string
{
    if (strlen($pass) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (strlen($pass) > 128) {
        return 'Password is too long.';
    }
    if (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

/** Create a reset token; returns plain token (to email) or null. */
function create_password_reset(string $userType, int $userId): string
{
    db_exec('UPDATE password_resets SET used_at = NOW() WHERE user_type = ? AND user_id = ? AND used_at IS NULL', [$userType, $userId]);
    $token = random_token(32);
    db_insert('password_resets', [
        'user_type' => $userType,
        'user_id' => $userId,
        'token_hash' => token_hash($token),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);
    return $token;
}

function find_password_reset(string $userType, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return db_one(
        'SELECT * FROM password_resets WHERE user_type = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
        [$userType, token_hash($token)]
    );
}

// ---- Administrators ------------------------------------------------------------

function current_admin(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    $cache = $id ? db_one(
        "SELECT a.*, r.slug AS role_slug, r.name AS role_name FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ? AND a.status = 'active'",
        [$id]
    ) : null;
    if ($id && !$cache) {
        unset($_SESSION['admin_id']);
    }
    if ($cache) {
        $_SESSION['admin_last_seen'] = time();
    }
    return $cache;
}

function admin_id(): ?int
{
    $a = current_admin();
    return $a ? (int) $a['id'] : null;
}

function is_super_admin(): bool
{
    $a = current_admin();
    return $a && $a['role_slug'] === 'super_admin';
}

function admin_permissions(): array
{
    static $perms = null;
    if ($perms !== null) {
        return $perms;
    }
    $a = current_admin();
    if (!$a) {
        return $perms = [];
    }
    if ($a['role_slug'] === 'super_admin') {
        return $perms = db_col('SELECT perm_key FROM admin_permissions');
    }
    return $perms = db_col(
        'SELECT p.perm_key FROM admin_role_permissions rp JOIN admin_permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
        [(int) $a['role_id']]
    );
}

function can(string $perm): bool
{
    return is_super_admin() || in_array($perm, admin_permissions(), true);
}

/** Server-side guard for every admin page/action. */
function require_admin(?string $perm = null): array
{
    $a = current_admin();
    if (!$a) {
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'Please sign in again.'], 401);
        }
        $_SESSION['admin_after_login'] = $_SERVER['REQUEST_URI'] ?? admin_url();
        redirect(admin_url('login'));
    }
    if ($a['must_change_password'] && !defined('ADMIN_PASSWORD_PAGE')) {
        flash('warning', 'Please set a new password before continuing.');
        redirect(admin_url('profile'));
    }
    if ($perm !== null && !can($perm)) {
        audit_log('access_denied', 'permission', null, ['perm' => $perm, 'uri' => $_SERVER['REQUEST_URI'] ?? '']);
        if (is_ajax()) {
            json_response(['ok' => false, 'message' => 'You do not have permission to do that.'], 403);
        }
        http_response_code(403);
        require ROOT_PATH . '/admin/_inc/forbidden.php';
        exit;
    }
    return $a;
}

function admin_login(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    $ident = $email . '|' . client_ip();
    if (!rate_limit('admin_login', $ident, 5, 900) || !rate_limit('admin_login_ip', client_ip(), 20, 900)) {
        return 'Too many sign-in attempts. Please wait 15 minutes.';
    }
    $a = db_one('SELECT * FROM admins WHERE email = ?', [$email]);
    if (!$a || !password_verify($password, $a['password_hash'])) {
        audit_log('login_failed', 'admin', $a ? (int) $a['id'] : null, ['email' => $email], $a ? (int) $a['id'] : null);
        return 'Invalid credentials.';
    }
    if ($a['status'] !== 'active') {
        return 'This administrator account is disabled.';
    }
    rate_limit_clear('admin_login', $ident);
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $a['id'];
    $_SESSION['admin_last_seen'] = time();
    db_exec('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), $a['id']]);
    if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $a['id']]);
    }
    audit_log('login', 'admin', (int) $a['id'], [], (int) $a['id']);
    return null;
}

function admin_logout(): void
{
    if ($id = admin_id()) {
        audit_log('logout', 'admin', $id);
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
    session_regenerate_id(true);
}

/** Record an administrative action. */
function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, array $details = [], ?int $adminId = null): void
{
    try {
        db_insert('admin_audit_logs', [
            'admin_id' => $adminId ?? admin_id(),
            'action' => mb_substr($action, 0, 80),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
