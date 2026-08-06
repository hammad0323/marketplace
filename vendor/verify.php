<?php
require __DIR__ . '/../config/config.php';

$token = $_GET['token'] ?? '';
$vendor = $token !== '' ? mp_verify_vendor_token($token) : null;

if ($vendor) {
    mp_log_activity('vendor', $vendor['id'], 'vendor.email_verified', 'vendor', $vendor['id']);
    mp_flash('success', 'Email verified — thanks!');
} else {
    mp_flash('error', 'That verification link is invalid or has expired. Request a new one below.');
}

mp_redirect(mp_current_vendor() ? 'dashboard.php' : 'login.php');
