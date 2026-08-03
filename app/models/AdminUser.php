<?php

class AdminUser extends Model
{
    protected string $table = 'admin_users';

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }
}
