<?php
require_once __DIR__ . '/includes/functions.php';

if (get_setting('google_login_enabled') !== '1' || !get_setting('google_client_id')) {
    flash_set('danger', 'Google login is not enabled.');
    redirect(url('login'));
}

$_SESSION['google_redirect'] = $_GET['redirect'] ?? 'account/dashboard';
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));

$params = http_build_query([
    'client_id' => get_setting('google_client_id'),
    'redirect_uri' => url('google-callback'),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $_SESSION['google_oauth_state'],
    'prompt' => 'select_account',
]);

redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
