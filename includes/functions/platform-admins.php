<?php
/**
 * platform_admins — the SaaS operator's own staff. See
 * includes/middlewares/platform-auth.php.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_platform_admin(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM platform_admins WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_platform_admin_by_email(string $email): ?array
{
    return mp_db_fetch_one('SELECT * FROM platform_admins WHERE email = ? LIMIT 1', [$email]);
}

function mp_touch_platform_admin_last_login(int $id): void
{
    mp_db_execute('UPDATE platform_admins SET last_login_at = NOW() WHERE id = ?', [$id]);
}
