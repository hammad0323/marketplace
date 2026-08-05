<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
mp_verify_csrf();

$items = mp_cart_items_for_customer($customer['id']);
if (!$items) {
    mp_redirect(ROUTE_CART . 'view.php');
}

$addressId = $_POST['address_id'] ?? 'new';
$shippingAddress = null;

if ($addressId !== 'new' && ctype_digit((string) $addressId)) {
    $shippingAddress = mp_find_address((int) $addressId, $customer['id']);
}

if (!$shippingAddress) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $line1 = trim($_POST['line1'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');

    if ($fullName === '' || $phone === '' || $line1 === '' || $city === '' || $country === '') {
        mp_flash('error', 'Please fill in the required shipping address fields.');
        mp_redirect('index.php');
    }

    $newAddressId = mp_insert_address([
        'customer_id'  => $customer['id'],
        'full_name'    => $fullName,
        'phone'        => $phone,
        'line1'        => $line1,
        'line2'        => trim($_POST['line2'] ?? '') ?: null,
        'city'         => $city,
        'state'        => trim($_POST['state'] ?? '') ?: null,
        'postal_code'  => trim($_POST['postal_code'] ?? '') ?: null,
        'country'      => $country,
        'is_default'   => 1,
    ]);
    $shippingAddress = mp_find_address($newAddressId, $customer['id']);
}

$paymentMethod = in_array($_POST['payment_method'] ?? '', ['cod', 'manual'], true) ? $_POST['payment_method'] : 'cod';

$error = null;
$order = mp_create_order($customer['id'], $shippingAddress, $items, $paymentMethod, $error);

if (!$order) {
    mp_flash('error', $error ?? 'Could not place your order.');
    mp_redirect('index.php');
}

mp_notify('order.placed', $customer['email'], ['order_number' => $order['order_number'], 'total' => $order['total_amount']]);

mp_flash('success', 'Order placed! Your order number is ' . $order['order_number'] . '.');
mp_redirect(ROUTE_ORDERS . 'details.php?number=' . urlencode($order['order_number']));
