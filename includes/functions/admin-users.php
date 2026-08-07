<?php
/**
 * admin_users — one tenant's own admin panel accounts. Distinct from
 * platform_admins, which manages tenants themselves and belongs to no
 * tenant (see includes/functions/tenants.php, platform/).
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

/** Defense-in-depth: reached via a raw id from a session cookie, so scoped even though the id alone already uniquely identifies a row. */
function mp_find_admin(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM admin_users WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, mp_tenant_id()]);
}

function mp_find_admin_by_email(string $email): ?array
{
    return mp_db_fetch_one('SELECT * FROM admin_users WHERE tenant_id = ? AND email = ? LIMIT 1', [mp_tenant_id(), $email]);
}

function mp_all_admins(): array
{
    return mp_db_fetch_all('SELECT * FROM admin_users WHERE tenant_id = ? ORDER BY created_at DESC', [mp_tenant_id()]);
}

function mp_insert_admin(array $data): int
{
    return mp_db_insert('admin_users', $data);
}

function mp_update_admin(int $id, array $data): void
{
    mp_db_update('admin_users', $data, 'id = ?', [$id]);
}

function mp_set_admin_active(int $id, bool $active): void
{
    mp_db_execute('UPDATE admin_users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
}

function mp_touch_admin_last_login(int $id): void
{
    mp_db_execute('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [$id]);
}
