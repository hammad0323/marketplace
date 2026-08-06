<?php
require __DIR__ . '/../config/config.php';

$token = $_GET['token'] ?? '';
$customer = $token !== '' ? mp_verify_customer_token($token) : null;

if ($customer) {
    mp_log_activity('customer', $customer['id'], 'customer.email_verified', 'customer', $customer['id']);
    mp_flash('success', 'Email verified — thanks!');
} else {
    mp_flash('error', 'That verification link is invalid or has expired.');
}

mp_redirect(mp_current_customer() ? ROUTE_HOME : ROUTE_CUSTOMER . 'login.php');
