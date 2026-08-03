<?php
/** Plain query functions for admin_users. */

function find_admin(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function find_admin_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}
