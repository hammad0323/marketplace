<?php
/**
 * crm_auth.php — authentication for the Sales CRM (a separate,
 * multi-tenant app from the public tools platform). Session-based,
 * same conventions as includes/auth.php but scoped to `businesses`
 * instead of `admins` — a signed-up business owner/salesperson, not
 * a platform admin.
 */

if (!defined('TOOLS_PLATFORM_ROOT')) {
    http_response_code(403);
    exit('Direct access is not permitted.');
}

function tp_business_register(string $businessName, string $ownerName, string $email, string $phone, string $password): array
{
    $email = strtolower(trim($email));
    if ($businessName === '' || $ownerName === '' || $email === '') {
        return ['success' => false, 'error' => 'Business name, your name and email are required.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    $existing = tp_query_one('SELECT id FROM businesses WHERE email = ?', 's', [$email]);
    if ($existing) {
        return ['success' => false, 'error' => 'An account with this email already exists.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $result = tp_execute(
        'INSERT INTO businesses (business_name, owner_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)',
        'sssss',
        [$businessName, $ownerName, $email, $phone, $hash]
    );
    if (!$result['success']) {
        return ['success' => false, 'error' => 'Could not create the account. Please try again.'];
    }

    session_regenerate_id(true);
    $_SESSION['business_id'] = $result['insert_id'];
    $_SESSION['business_name'] = $businessName;

    return ['success' => true, 'error' => null];
}

function tp_business_attempt_login(string $email, string $password): array
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!tp_rate_limit('crm_login:' . $ip, 8, 300)) {
        return ['success' => false, 'error' => 'Too many attempts. Please wait a few minutes and try again.'];
    }

    $email = strtolower(trim($email));
    $business = tp_query_one('SELECT * FROM businesses WHERE email = ? LIMIT 1', 's', [$email]);
    if (!$business || $business['status'] !== 'active' || !password_verify($password, $business['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    session_regenerate_id(true);
    $_SESSION['business_id'] = (int) $business['id'];
    $_SESSION['business_name'] = $business['business_name'];

    tp_execute('UPDATE businesses SET last_login_at = NOW() WHERE id = ?', 'i', [$business['id']]);

    return ['success' => true, 'error' => null];
}

function tp_business_logout(): void
{
    unset($_SESSION['business_id'], $_SESSION['business_name']);
    session_regenerate_id(true);
}

function tp_current_business(): ?array
{
    if (empty($_SESSION['business_id'])) {
        return null;
    }
    static $business = null;
    if ($business === null) {
        $business = tp_query_one('SELECT id, business_name, owner_name, email, phone, plan FROM businesses WHERE id = ?', 'i', [$_SESSION['business_id']]);
    }
    return $business;
}

function tp_current_business_id(): int
{
    return (int) ($_SESSION['business_id'] ?? 0);
}

function tp_is_business_logged_in(): bool
{
    return !empty($_SESSION['business_id']);
}

/** Call at the very top of every crm/*.php page (after config.php). */
function require_business(): void
{
    if (!tp_is_business_logged_in()) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header('Location: ' . tp_url('crm/login.php?redirect=' . $redirect));
        exit;
    }
}
