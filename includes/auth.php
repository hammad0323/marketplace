<?php
/**
 * Customer + admin authentication and role-based permissions.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

/* ==================================================================
 * CUSTOMERS
 * ================================================================== */
function customer_id(): int
{
    return (int) ($_SESSION['customer_id'] ?? 0);
}

function current_customer(): ?array
{
    static $c = false;
    if ($c === false) {
        $c = customer_id() ? db_row('SELECT * FROM customers WHERE id = ? AND is_active = 1 AND deleted_at IS NULL', [customer_id()]) : null;
        if (customer_id() && !$c) {
            unset($_SESSION['customer_id']);
        }
    }
    return $c;
}

function customer_login(array $customer): void
{
    session_regenerate_id(true);
    $_SESSION['customer_id'] = (int) $customer['id'];
    db_exec('UPDATE customers SET last_login_at = NOW() WHERE id = ?', [$customer['id']]);
    cart_merge_on_login((int) $customer['id']);
}

function customer_logout(): void
{
    unset($_SESSION['customer_id']);
    session_regenerate_id(true);
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? url('my-account/');
        flash('info', 'Please log in to continue.');
        redirect('login/');
    }
    return $c;
}

/**
 * Attempt customer login by phone or email.
 * @return array|string customer row or error message
 */
function customer_attempt(string $login, string $password)
{
    if (login_throttled('customer', $login)) {
        return 'Too many failed attempts. Please wait ' . LOGIN_WINDOW_MINUTES . ' minutes and try again.';
    }
    $isEmail = strpos($login, '@') !== false;
    $c = $isEmail
        ? db_row('SELECT * FROM customers WHERE email = ? AND deleted_at IS NULL', [mb_strtolower($login)])
        : db_row('SELECT * FROM customers WHERE phone = ? AND deleted_at IS NULL', [normalize_phone($login)]);
    if (!$c || !$c['password_hash'] || !password_verify($password, $c['password_hash'])) {
        login_failed('customer', $login);
        return 'Incorrect phone/email or password.';
    }
    if (!$c['is_active']) {
        return 'Your account has been disabled. Please contact the store.';
    }
    if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $c['id']]);
    }
    login_clear('customer', $login);
    return $c;
}

/**
 * Register (or upgrade a guest/POS customer with the same phone).
 * @return array|string customer row or error
 */
function customer_register(string $name, string $phone, string $email, string $password)
{
    $phone = normalize_phone($phone);
    $email = mb_strtolower(trim($email));
    if (mb_strlen($name) < 2) return 'Please enter your name.';
    if (!valid_phone($phone)) return 'Please enter a valid mobile number.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return 'Please enter a valid email address.';
    if (strlen($password) < 6) return 'Password must be at least 6 characters.';

    $existing = db_row('SELECT * FROM customers WHERE phone = ?', [$phone]);
    if ($existing && $existing['is_registered']) {
        return 'An account with this mobile number already exists. Please log in.';
    }
    if ($email !== '') {
        $emailOwner = db_val('SELECT id FROM customers WHERE email = ? AND id <> ?', [$email, $existing['id'] ?? 0]);
        if ($emailOwner) return 'This email is already registered.';
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if ($existing) {
        db_exec('UPDATE customers SET name = ?, email = ?, password_hash = ?, is_registered = 1 WHERE id = ?', [$name, $email ?: null, $hash, $existing['id']]);
        $id = (int) $existing['id'];
    } else {
        $id = db_insert('INSERT INTO customers (name, phone, email, password_hash, is_registered, source) VALUES (?, ?, ?, ?, 1, ?)', [$name, $phone, $email ?: null, $hash, 'web']);
    }
    return db_row('SELECT * FROM customers WHERE id = ?', [$id]);
}

/** Find or create a (guest/POS) customer record by phone. Returns id. */
function customer_find_or_create(string $name, string $phone, ?string $email = null, string $source = 'guest'): int
{
    $phone = normalize_phone($phone);
    $row = db_row('SELECT id, email FROM customers WHERE phone = ?', [$phone]);
    if ($row) {
        if ($email && !$row['email'] && !db_val('SELECT id FROM customers WHERE email = ?', [$email])) {
            db_exec('UPDATE customers SET email = ? WHERE id = ?', [$email, $row['id']]);
        }
        return (int) $row['id'];
    }
    if ($email && db_val('SELECT id FROM customers WHERE email = ?', [$email])) {
        $email = null;
    }
    return db_insert('INSERT INTO customers (name, phone, email, source) VALUES (?, ?, ?, ?)', [$name, $phone, $email ?: null, $source]);
}

/* ==================================================================
 * ADMINS
 * ================================================================== */

/** All permission keys used across the admin panel. */
function admin_permission_list(): array
{
    return [
        'dashboard'  => 'View dashboard',
        'categories' => 'Manage categories',
        'products'   => 'Manage products & brands',
        'import'     => 'Bulk import / export',
        'inventory'  => 'Inventory, purchases & suppliers',
        'orders'     => 'Manage orders & rashan orders',
        'customers'  => 'Manage customers',
        'content'    => 'Banners, homepage, promotions, pages, testimonials',
        'coupons'    => 'Coupons & discounts',
        'delivery'   => 'Delivery, pickup & payment settings',
        'reviews'    => 'Moderate reviews',
        'seo'        => 'SEO management',
        'pos'        => 'Use POS',
        'reports'    => 'View reports',
        'admins'     => 'Manage admin users & roles',
        'settings'   => 'Website settings',
    ];
}

function admin_id(): int
{
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function current_admin(): ?array
{
    static $a = false;
    if ($a === false) {
        $a = null;
        if (admin_id()) {
            $a = db_row('SELECT a.*, r.name AS role_name, r.is_super FROM admins a JOIN admin_roles r ON r.id = a.role_id
                         WHERE a.id = ? AND a.is_active = 1 AND a.deleted_at IS NULL', [admin_id()]);
            if ($a) {
                $a['permissions'] = $a['is_super'] ? array_keys(admin_permission_list())
                    : db_col('SELECT permission FROM admin_permissions WHERE role_id = ?', [$a['role_id']]);
            } else {
                unset($_SESSION['admin_id']);
            }
        }
    }
    return $a;
}

function admin_can(string $perm): bool
{
    $a = current_admin();
    return $a && ($a['is_super'] || in_array($perm, $a['permissions'], true));
}

/**
 * @return array|string admin row or error
 */
function admin_attempt(string $email, string $password)
{
    $email = mb_strtolower(trim($email));
    if (login_throttled('admin', $email)) {
        return 'Too many failed attempts. Try again in ' . LOGIN_WINDOW_MINUTES . ' minutes.';
    }
    $a = db_row('SELECT * FROM admins WHERE email = ? AND deleted_at IS NULL', [$email]);
    if (!$a || !password_verify($password, $a['password_hash'])) {
        login_failed('admin', $email);
        // Constant-ish time when user is missing
        if (!$a) password_verify($password, '$2y$10$abcdefghijklmnopqrstuuJ4f9Vb3f0w7cRrCqvjKk1a0bH5mIu2');
        return 'Invalid email or password.';
    }
    if (!$a['is_active']) {
        return 'This admin account is disabled.';
    }
    login_clear('admin', $email);
    if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $a['id']]);
    }
    return $a;
}

function admin_login(array $a): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $a['id'];
    $_SESSION['admin_ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['admin_last'] = time();
    db_exec('UPDATE admins SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), $a['id']]);
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_ua'], $_SESSION['admin_last']);
    session_regenerate_id(true);
}

/** Validate admin session (UA binding + 2h idle timeout). */
function admin_session_valid(): bool
{
    if (!admin_id()) return false;
    if (($_SESSION['admin_ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) return false;
    if (time() - ($_SESSION['admin_last'] ?? 0) > 7200) return false;
    $_SESSION['admin_last'] = time();
    return current_admin() !== null;
}
