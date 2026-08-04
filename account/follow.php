<?php
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    mp_redirect('/customer/login');
}

mp_verify_csrf();

$vendor = mp_find_vendor((int) $id);
if ($vendor) {
    mp_customer_follow_vendor($customerId, $vendor['id']);
    mp_flash('success', 'You are now following ' . $vendor['store_name'] . '.');
}

mp_redirect($_POST['redirect_to'] ?? '/');
