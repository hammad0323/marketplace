<?php
/**
 * Session-based auth for the two guarded areas of the app: vendors
 * (dashboard) and admins (approval screens). Deliberately simple —
 * no roles/permissions system, since that belongs to the full admin
 * panel build-out, not the core marketplace architecture.
 */

class Auth
{
    public static function loginVendor(array $vendor): void
    {
        $_SESSION['vendor_id'] = $vendor['id'];
    }

    public static function vendor(): ?array
    {
        if (empty($_SESSION['vendor_id'])) {
            return null;
        }
        return (new Vendor())->find($_SESSION['vendor_id']);
    }

    public static function requireVendor(): array
    {
        $vendor = self::vendor();
        if (!$vendor) {
            redirect('/vendor/login');
        }
        return $vendor;
    }

    public static function logoutVendor(): void
    {
        unset($_SESSION['vendor_id']);
    }

    public static function loginAdmin(array $admin): void
    {
        $_SESSION['admin_id'] = $admin['id'];
    }

    public static function admin(): ?array
    {
        if (empty($_SESSION['admin_id'])) {
            return null;
        }
        return (new AdminUser())->find($_SESSION['admin_id']);
    }

    public static function requireAdmin(): array
    {
        $admin = self::admin();
        if (!$admin) {
            redirect('/admin/login');
        }
        return $admin;
    }

    public static function logoutAdmin(): void
    {
        unset($_SESSION['admin_id']);
    }
}
