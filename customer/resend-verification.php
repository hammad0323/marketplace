<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
mp_verify_csrf();

if ($customer['email_verified_at']) {
    mp_flash('success', 'Your email is already verified.');
    mp_redirect(ROUTE_HOME);
}

$verifyToken = mp_set_customer_verification_token($customer['id']);
$verifyUrl = ROUTE_CUSTOMER . 'verify.php?token=' . $verifyToken;
mp_notify('customer.welcome', $customer['email'], ['name' => $customer['name'], 'verify_url' => $verifyUrl]);

$_SESSION['_just_registered_verify_url'] = $verifyUrl;
mp_redirect(ROUTE_CUSTOMER . 'registered.php');
