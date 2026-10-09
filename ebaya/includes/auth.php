<?php
/** Customer and admin authentication, role-based permissions, audit log. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

// ---------------------------------------------------------------------
// Customers
// ---------------------------------------------------------------------
function customer_id(): ?int
{
    return !empty($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : null;
}

function current_customer(): ?array
{
    static $c = false;
    if ($c === false) {
        $id = customer_id();
        $c = $id ? db_one('SELECT id, name, email, phone, status, marketing_opt_in, created_at FROM customers WHERE id = ?', [$id]) : null;
        if ($c && $c['status'] !== 'active') {
            customer_logout();
            $c = null;
        }
    }
    return $c;
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        flash('info', 'Please sign in to continue.');
        redirect('account/login?return=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
    return $c;
}

function customer_login(array $customer): void
{
    $guestCartId = cart_id(false);
    $guestWishlist = $_SESSION['wishlist'] ?? [];
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int)$customer['id'];
    db_exec('UPDATE customers SET last_login_at = NOW() WHERE id = ?', [(int)$customer['id']]);
    cart_merge_on_login($guestCartId, (int)$customer['id']);
    wishlist_merge_on_login($guestWishlist, (int)$customer['id']);
}

function customer_logout(): void
{
    unset($_SESSION['customer_id'], $_SESSION['wishlist']);
    session_regenerate_id(true);
    cart_forget_cookie();
}

function customer_attempt_login(string $email, string $password): ?array
{
    $c = db_one('SELECT * FROM customers WHERE email = ?', [strtolower($email)]);
    if (!$c || !password_verify($password, $c['password_hash'])) {
        // Equalise timing for unknown emails.
        if (!$c) password_verify($password, '$2y$10$usesomesillystringforsalte8RdMBEfKcf4j5r1n3mSz3rTVSKO');
        return null;
    }
    if ($c['status'] !== 'active') return null;
    if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $c['id']]);
    }
    return $c;
}

function customer_create(string $name, string $email, string $phone, string $password, bool $optIn = false): int
{
    return db_insert('INSERT INTO customers (name, email, phone, password_hash, marketing_opt_in) VALUES (?, ?, ?, ?, ?)',
        [$name, strtolower($email), $phone, password_hash($password, PASSWORD_DEFAULT), $optIn ? 1 : 0]);
}

// ---------------------------------------------------------------------
// Password reset (shared by customers and admins)
// ---------------------------------------------------------------------
function password_reset_create(string $type, int $userId): string
{
    $token = random_token(32);
    db_exec('UPDATE password_resets SET used_at = NOW() WHERE user_type = ? AND user_id = ? AND used_at IS NULL', [$type, $userId]);
    db_insert('INSERT INTO password_resets (user_type, user_id, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
        [$type, $userId, hash('sha256', $token)]);
    return $token;
}

function password_reset_find(string $type, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    return db_one('SELECT * FROM password_resets WHERE user_type = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
        [$type, hash('sha256', $token)]);
}

// ---------------------------------------------------------------------
// Admins
// ---------------------------------------------------------------------
function admin_id(): ?int
{
    return !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;
}

function current_admin(): ?array
{
    static $a = false;
    if ($a === false) {
        $id = admin_id();
        $a = $id ? db_one('SELECT a.id, a.name, a.email, a.status, a.must_change_password, a.role_id, r.slug AS role_slug, r.name AS role_name, r.is_super
                          FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ?', [$id]) : null;
        if ($a && $a['status'] !== 'active') {
            unset($_SESSION['admin_id']);
            $a = null;
        }
        // Idle timeout: 2 hours.
        if ($a && isset($_SESSION['admin_seen']) && time() - $_SESSION['admin_seen'] > 7200) {
            unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
            $a = null;
        }
        if ($a) $_SESSION['admin_seen'] = time();
    }
    return $a;
}

function admin_permissions(): array
{
    static $perms = null;
    if ($perms !== null) return $perms;
    $a = current_admin();
    if (!$a) return $perms = [];
    if ($a['is_super']) return $perms = ['*'];
    return $perms = db_col('SELECT p.perm_key FROM admin_role_permissions rp JOIN admin_permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [(int)$a['role_id']]);
}

function can(string $perm): bool
{
    $p = admin_permissions();
    return in_array('*', $p, true) || in_array($perm, $p, true);
}

/** Call at the top of every admin page. Enforces login and permission server-side. */
function require_admin(?string $perm = null): array
{
    $a = current_admin();
    if (!$a) {
        if (is_ajax()) json_out(['ok' => false, 'message' => 'Please sign in again.'], 401);
        redirect(admin_url('login?return=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
    }
    $self = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($a['must_change_password'] && !in_array($self, ['profile.php', 'logout.php'], true)) {
        flash('warning', 'Please set a new password before continuing.');
        redirect(admin_url('profile'));
    }
    if ($perm && !can($perm)) {
        if (is_ajax()) json_out(['ok' => false, 'message' => 'You do not have permission for this action.'], 403);
        http_response_code(403);
        $GLOBALS['admin_title'] = 'Access denied';
        require ROOT_PATH . '/admin/partials/header.php';
        echo '<div class="alert alert-warning">Your role (' . e($a['role_name']) . ') does not have permission to access this area.</div>';
        require ROOT_PATH . '/admin/partials/footer.php';
        exit;
    }
    return $a;
}

function admin_attempt_login(string $email, string $password): ?array
{
    $a = db_one('SELECT * FROM admins WHERE email = ?', [strtolower($email)]);
    if (!$a || !password_verify($password, $a['password_hash'])) {
        if (!$a) password_verify($password, '$2y$10$usesomesillystringforsalte8RdMBEfKcf4j5r1n3mSz3rTVSKO');
        return null;
    }
    if ($a['status'] !== 'active') return null;
    return $a;
}

function admin_login(array $admin): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_seen'] = time();
    db_exec('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), (int)$admin['id']]);
    audit('login', 'admin', (int)$admin['id']);
}

function audit(string $action, ?string $entity = null, ?int $entityId = null, $details = null): void
{
    if (is_array($details)) $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    db_insert('INSERT INTO admin_audit_logs (admin_id, action, entity_type, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)',
        [admin_id(), $action, $entity, $entityId, $details !== null ? mb_substr((string)$details, 0, 60000) : null, client_ip()]);
}
