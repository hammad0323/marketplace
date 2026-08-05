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
