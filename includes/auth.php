<?php
/**
 * Session-based auth for the two guarded areas of the app: vendors
 * (dashboard) and admins (approval screens). Deliberately simple —
 * no roles/permissions system, since that belongs to the full admin
 * panel build-out, not the core marketplace architecture.
 */

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
        mp_redirect('/vendor/login');
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
        mp_redirect('/admin/login');
    }
    return $admin;
}

function mp_logout_admin(): void
{
    unset($_SESSION['admin_id']);
}
