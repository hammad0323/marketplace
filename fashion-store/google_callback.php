<?php
require_once __DIR__ . '/includes/functions.php';

if (get_setting('google_login_enabled') !== '1') redirect(BASE_URL . '/login.php');

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
if (!$code || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
    flash_set('danger', 'Google login failed. Please try again.');
    redirect(BASE_URL . '/login.php');
}
unset($_SESSION['google_oauth_state']);

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => get_setting('google_client_id'),
        'client_secret' => get_setting('google_client_secret'),
        'redirect_uri' => BASE_URL . '/google_callback.php',
        'grant_type' => 'authorization_code',
    ]),
]);
$tokenResponse = json_decode(curl_exec($ch) ?: '{}', true);
curl_close($ch);

if (empty($tokenResponse['access_token'])) {
    flash_set('danger', 'Could not verify your Google account. Please try again.');
    redirect(BASE_URL . '/login.php');
}

$ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenResponse['access_token']],
]);
$userInfo = json_decode(curl_exec($ch) ?: '{}', true);
curl_close($ch);

if (empty($userInfo['email'])) {
    flash_set('danger', 'Could not retrieve your Google account details.');
    redirect(BASE_URL . '/login.php');
}

$email = $userInfo['email'];
$googleId = $userInfo['sub'] ?? '';
$name = $userInfo['name'] ?? $email;

$stmt = mysqli_prepare($mysqli, "SELECT * FROM customers WHERE email = ? OR google_id = ?");
mysqli_stmt_bind_param($stmt, 'ss', $email, $googleId);
mysqli_stmt_execute($stmt);
$customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($customer) {
    if (!$customer['google_id']) {
        mysqli_query($mysqli, "UPDATE customers SET google_id = '" . mysqli_real_escape_string($mysqli, $googleId) . "' WHERE id = " . (int)$customer['id']);
    }
    $customerId = $customer['id'];
} else {
    $stmt = mysqli_prepare($mysqli, "INSERT INTO customers (name, email, google_id) VALUES (?,?,?)");
    mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $googleId);
    mysqli_stmt_execute($stmt);
    $customerId = mysqli_insert_id($mysqli);
}

session_regenerate_id(true);
$_SESSION['customer_id'] = $customerId;
merge_guest_cart_into_customer($mysqli, $customerId);

$redirect = $_SESSION['google_redirect'] ?? 'account/dashboard.php';
unset($_SESSION['google_redirect']);
redirect(BASE_URL . '/' . ltrim($redirect, '/'));
