<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();
mp_verify_csrf();

$vendor = mp_find_vendor((int) ($_GET['id'] ?? 0));
$reason = trim($_POST['reason'] ?? 'Not specified');

if ($vendor) {
    mp_reject_vendor($vendor['id'], $reason);
    mp_notify('vendor.rejected', $vendor['email'], ['store_name' => $vendor['store_name'], 'reason' => $reason]);
    mp_flash('success', $vendor['store_name'] . ' rejected.');
}

mp_redirect('vendors.php');
