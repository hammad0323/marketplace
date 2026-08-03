<?php
$customerId = $_SESSION['customer_id'] ?? null;
if (!$customerId) {
    redirect('/customer/login');
}

verify_csrf();

$vendor = find_vendor((int) $id);
if ($vendor) {
    customer_follow_vendor($customerId, $vendor['id']);
    flash('success', 'You are now following ' . $vendor['store_name'] . '.');
}

redirect($_POST['redirect_to'] ?? '/');
