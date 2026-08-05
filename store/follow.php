<?php
require __DIR__ . '/../config/config.php';

$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    mp_redirect(ROUTE_CUSTOMER . 'login.php');
}

mp_verify_csrf();

$vendorId = (int) ($_GET['vendor_id'] ?? 0);
$vendor = mp_find_vendor($vendorId);
if ($vendor) {
    mp_customer_follow_vendor($customerId, $vendor['id']);
    mp_flash('success', 'You are now following ' . $vendor['store_name'] . '.');
}

mp_redirect($_POST['redirect_to'] ?? ROUTE_HOME);
