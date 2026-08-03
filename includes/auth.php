<?php
/**
 * Session-based auth for the two guarded areas of the app: vendors
 * (dashboard) and admins (approval screens). Deliberately simple —
 * no roles/permissions system, since that belongs to the full admin
 * panel build-out, not the core marketplace architecture.
 */

function login_vendor(array $vendor): void
{
    $_SESSION['vendor_id'] = $vendor['id'];
}

function current_vendor(): ?array
{
    if (empty($_SESSION['vendor_id'])) {
        return null;
    }
    return find_vendor((int) $_SESSION['vendor_id']);
}

function require_vendor(): array
{
    $vendor = current_vendor();
    if (!$vendor) {
        redirect('/vendor/login');
    }
    return $vendor;
}

function logout_vendor(): void
{
    unset($_SESSION['vendor_id']);
}

function login_admin(array $admin): void
{
    $_SESSION['admin_id'] = $admin['id'];
}

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    return find_admin((int) $_SESSION['admin_id']);
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('/admin/login');
    }
    return $admin;
}

function logout_admin(): void
{
    unset($_SESSION['admin_id']);
}
