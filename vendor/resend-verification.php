<?php
require __DIR__ . '/../config/config.php';

$vendor = mp_require_vendor();
mp_verify_csrf();

if ($vendor['email_verified_at']) {
    mp_flash('success', 'Your email is already verified.');
    mp_redirect('dashboard.php');
}

$verifyToken = mp_set_vendor_verification_token($vendor['id']);
$verifyUrl = ROUTE_VENDOR . 'verify.php?token=' . $verifyToken;
mp_notify('vendor.welcome', $vendor['email'], ['store_name' => $vendor['store_name'], 'verify_url' => $verifyUrl]);

$_SESSION['_just_registered_verify_url'] = $verifyUrl;
mp_flash('success', 'A new verification link has been sent.');
mp_redirect('registered.php');
