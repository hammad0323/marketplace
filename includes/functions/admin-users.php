<?php
/**
 * admin_users
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_find_admin(int $id): ?array
{
    return mp_db_fetch_one('SELECT * FROM admin_users WHERE id = ? LIMIT 1', [$id]);
}

function mp_find_admin_by_email(string $email): ?array
{
    return mp_db_fetch_one('SELECT * FROM admin_users WHERE email = ? LIMIT 1', [$email]);
}

function mp_all_admins(): array
{
    return mp_db_fetch_all('SELECT * FROM admin_users ORDER BY created_at DESC');
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
