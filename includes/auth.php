<?php
/**
 * Customer and administrator authentication, plus admin role permissions.
 */

// ---------------------------------------------------------------------
//  Customers
// ---------------------------------------------------------------------
function current_customer(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $id = (int) ($_SESSION['customer_id'] ?? 0);
    $cached = $id ? db_row('SELECT id, first_name, last_name, email, phone, is_active, newsletter, created_at FROM customers WHERE id = ? AND is_active = 1', [$id]) : null;
    if ($id && !$cached) {
        unset($_SESSION['customer_id']);
    }
    return $cached;
}

function customer_id(): ?int
{
    $c = current_customer();
    return $c ? (int) $c['id'] : null;
}

function customer_login(array $customer): void
{
    $guestCartToken = $_COOKIE['uc_cart'] ?? null;
    $guestWishToken = $_COOKIE['uc_wish'] ?? null;
    uc_regenerate_session();
    $_SESSION['customer_id'] = (int) $customer['id'];
    db_exec('UPDATE customers SET last_login_at = NOW() WHERE id = ?', [(int) $customer['id']]);
    cart_merge_guest((int) $customer['id'], $guestCartToken);
    wishlist_merge_guest((int) $customer['id'], $guestWishToken);
}

function customer_logout(): void
{
    unset($_SESSION['customer_id']);
    uc_regenerate_session();
    cart_forget_cookie();
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Please sign in to continue.');
        redirect('account/login');
    }
    return $c;
}

function customer_create(array $d): int
{
    return db_insert('customers', [
        'first_name' => $d['first_name'],
        'last_name' => $d['last_name'] ?? '',
        'email' => strtolower($d['email']),
        'phone' => $d['phone'] ?? null,
        'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT),
        'newsletter' => !empty($d['newsletter']) ? 1 : 0,
    ]);
}

function password_strong_enough(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain letters and numbers.';
    }
    return null;
}

/** Creates a single-use reset token and returns the raw token (only its hash is stored). */
function password_reset_create(string $type, int $userId): string
{
    $token = random_token(32);
    db_exec('UPDATE password_resets SET used_at = NOW() WHERE user_type = ? AND user_id = ? AND used_at IS NULL', [$type, $userId]);
    db_insert('password_resets', [
        'user_type' => $type,
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    ]);
    return $token;
}

function password_reset_find(string $type, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    return db_row(
        'SELECT * FROM password_resets WHERE user_type = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()',
        [$type, hash('sha256', $token)]
    );
}

// ---------------------------------------------------------------------
//  Administrators
// ---------------------------------------------------------------------
function current_admin(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $id = (int) ($_SESSION['admin_id'] ?? 0);
    $cached = null;
    if ($id) {
        // Idle timeout: 2 hours
        if (time() - (int) ($_SESSION['admin_seen'] ?? 0) > 7200) {
            unset($_SESSION['admin_id']);
            return null;
        }
        $_SESSION['admin_seen'] = time();
        $cached = db_row(
            'SELECT a.id, a.name, a.email, a.role_id, a.must_change_password, r.slug AS role_slug, r.name AS role_name
             FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ? AND a.is_active = 1',
            [$id]
        );
        if (!$cached) {
            unset($_SESSION['admin_id']);
        }
    }
    return $cached;
}

function admin_id(): ?int
{
    $a = current_admin();
    return $a ? (int) $a['id'] : null;
}

function admin_permissions(): array
{
    static $perms = null;
    $admin = current_admin();
    if (!$admin) {
        return [];
    }
    if ($perms === null) {
        $perms = db_col(
            'SELECT p.perm_key FROM admin_role_permissions rp JOIN admin_permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?',
            [(int) $admin['role_id']]
        );
    }
    return $perms;
}

/** Super Admins implicitly hold every permission. */
function admin_can(string $perm): bool
{
    $admin = current_admin();
    if (!$admin) {
        return false;
    }
    if ($admin['role_slug'] === 'super-admin') {
        return true;
    }
    return in_array($perm, admin_permissions(), true);
}

function admin_login(array $admin): void
{
    uc_regenerate_session();
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_seen'] = time();
    db_exec('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), (int) $admin['id']]);
    audit_log('login', 'admin', (int) $admin['id']);
}

function admin_logout(): void
{
    if (admin_id()) {
        audit_log('logout', 'admin', admin_id());
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
    uc_regenerate_session();
}

/** Records an important admin change. $details may be an array (stored as JSON). */
function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, $details = null): void
{
    try {
        db_insert('admin_audit_logs', [
            'admin_id' => admin_id(),
            'action' => substr($action, 0, 80),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details,
            'ip_address' => client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
