<?php
/**
 * Session-based auth for platform_admins — the SaaS operator's own
 * staff, who manage tenants (platform/) and belong to no tenant
 * themselves. Mirrors includes/middlewares/auth.php's admin
 * functions exactly, just keyed on a separate session key
 * ($_SESSION['platform_admin_id']) and a separate table so a tenant
 * admin session and a platform operator session can never be confused
 * with each other.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_login_platform_admin(array $platformAdmin): void
{
    $_SESSION['platform_admin_id'] = $platformAdmin['id'];
}

function mp_current_platform_admin(): ?array
{
    if (empty($_SESSION['platform_admin_id'])) {
        return null;
    }
    $platformAdmin = mp_find_platform_admin((int) $_SESSION['platform_admin_id']);

    if ($platformAdmin && !$platformAdmin['is_active']) {
        mp_logout_platform_admin();
        return null;
    }

    return $platformAdmin;
}

function mp_require_platform_admin(): array
{
    $platformAdmin = mp_current_platform_admin();
    if (!$platformAdmin) {
        mp_redirect(ROUTE_PLATFORM . 'login.php');
    }
    return $platformAdmin;
}

function mp_logout_platform_admin(): void
{
    unset($_SESSION['platform_admin_id']);
}
