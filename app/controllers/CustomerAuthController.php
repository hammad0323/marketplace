<?php

class CustomerAuthController
{
    public function showRegister(): void
    {
        View::render('customer/register', ['title' => 'Create Account'], 'main');
    }

    public function register(): void
    {
        verify_csrf();

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $email === '' || strlen($password) < 8) {
            flash('error', 'Please fill in all fields (password must be at least 8 characters).');
            redirect('/customer/register');
        }

        $customerModel = new Customer();
        if ($customerModel->findByEmail($email)) {
            flash('error', 'An account with that email already exists.');
            redirect('/customer/register');
        }

        $customerId = $customerModel->insert([
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        Notifier::log('customer.welcome', $email, ['name' => $name]);

        $_SESSION['customer_id'] = $customerId;
        redirect($_POST['redirect_to'] ?? '/');
    }

    public function showLogin(): void
    {
        View::render('customer/login', ['title' => 'Login'], 'main');
    }

    public function login(): void
    {
        verify_csrf();

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $customer = (new Customer())->findByEmail($email);
        if (!$customer || !password_verify($password, $customer['password_hash'])) {
            flash('error', 'Invalid email or password.');
            redirect('/customer/login');
        }

        $_SESSION['customer_id'] = $customer['id'];
        redirect($_POST['redirect_to'] ?? '/');
    }

    public function logout(): void
    {
        unset($_SESSION['customer_id']);
        redirect('/');
    }
}
