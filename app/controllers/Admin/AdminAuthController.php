<?php

class AdminAuthController
{
    public function showLogin(): void
    {
        View::render('admin/login', ['title' => 'Admin Login'], 'admin');
    }

    public function login(): void
    {
        verify_csrf();

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $admin = (new AdminUser())->findByEmail($email);
        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            flash('error', 'Invalid email or password.');
            redirect('/admin/login');
        }

        Auth::loginAdmin($admin);
        redirect('/admin');
    }

    public function logout(): void
    {
        Auth::logoutAdmin();
        redirect('/admin/login');
    }
}
