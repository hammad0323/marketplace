<?php
/**
 * Session-based auth "middleware" for vendors and admins — checked at
 * the top of any page that needs a logged-in vendor/admin. No
 * roles/permissions system — deliberately simple.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_login_vendor(array $vendor): void
{
    $_SESSION['vendor_id'] = $vendor['id'];
}

function mp_current_vendor(): ?array
{
    if (empty($_SESSION['vendor_id'])) {
        return null;
    }
    return mp_find_vendor((int) $_SESSION['vendor_id']);
}

function mp_require_vendor(): array
{
    $vendor = mp_current_vendor();
    if (!$vendor) {
        mp_redirect(ROUTE_VENDOR . 'login.php');
    }
    return $vendor;
}

function mp_logout_vendor(): void
{
    unset($_SESSION['vendor_id']);
}

function mp_login_admin(array $admin): void
{
    $_SESSION['admin_id'] = $admin['id'];
}

function mp_current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return mp_find_admin((int) $_SESSION['admin_id']);
}

function mp_require_admin(): array
{
    $admin = mp_current_admin();
    if (!$admin) {
        mp_redirect(ROUTE_ADMIN . 'login.php');
    }
    return $admin;
}

function mp_logout_admin(): void
{
    unset($_SESSION['admin_id']);
}
