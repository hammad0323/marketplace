<?php
/**
 * Shop owner web dashboard bootstrap. Signs in with the same tokens as the app (httpOnly cookie "ph_owner"),
 * and changes data through the same functions as the API, so web and app always behave the same.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === '_init.php') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/core/bootstrap.php';
require PH_ROOT . '/core/panel.php';
require PH_ROOT . '/api/modules/auth.php';
require PH_ROOT . '/api/modules/owner.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('ph_owner_s');
    session_set_cookie_params(['lifetime' => 0, 'path' => app_base_path() . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
send_security_headers();
header('Cache-Control: no-store');
if (is_post()) {
    csrf_check();
}
$OWNER = null;
if (!in_array(basename($_SERVER['SCRIPT_NAME']), ['login.php', 'forgot.php', 'reset.php'], true)) {
    $OWNER = current_owner();
    if (!$OWNER) {
        redirect('owner/login.php');
    }
    if ((int) $OWNER['user']['must_change_password'] && !in_array(basename($_SERVER['SCRIPT_NAME']), ['password.php', 'logout.php'], true)) {
        flash('warning', 'Welcome! Please choose your own password to continue.');
        redirect('owner/password.php');
    }
}

function owner_shop_id(): int
{
    return (int) $GLOBALS['OWNER']['shop']['id'];
}

/** Stops a POST when the subscription does not allow changes. */
function owner_require_active(string $back): void
{
    if (!subscription_allows_work(owner_shop_id(), $GLOBALS['OWNER']['shop'])) {
        flash('error', 'Your subscription has expired or is suspended. Please renew it to make changes.');
        redirect($back);
    }
}

function owner_header(string $title): void
{
    $o = $GLOBALS['OWNER'];
    $sid = owner_shop_id();
    $new = (int) db_val("SELECT COUNT(*) FROM orders WHERE shop_id = ? AND status IN ('submitted','awaiting_approval')", [$sid]);
    $st = subscription_state($sid, $o['shop']);
    $banner = '';
    if (in_array($st['state'], ['expired', 'none', 'suspended'], true)) {
        $banner = '<div class="p-banner p-banner-danger"><i class="bi bi-exclamation-octagon fs-5"></i><div><strong>' . e($st['label']) . '.</strong> Customers cannot place online orders and changes are locked until your subscription is renewed. <a href="' . e(path('owner/subscription.php')) . '">Details</a></div></div>';
    } elseif ($st['state'] === 'grace' || ($st['days_left'] !== null && $st['days_left'] <= 5)) {
        $banner = '<div class="p-banner p-banner-warning"><i class="bi bi-hourglass-split fs-5"></i><div>' . ($st['state'] === 'grace' ? e($st['label']) : 'Your subscription ends on <strong>' . e(format_date($st['end_date'])) . '</strong>') . '. Renew to keep receiving orders. <a href="' . e(path('owner/subscription.php')) . '">Renew</a></div></div>';
    }
    if (!(int) $o['shop']['accepting_orders'] && !$banner) {
        $banner = '<div class="p-banner p-banner-warning"><i class="bi bi-pause-circle fs-5"></i><div>Online orders are <strong>paused</strong>. Customers see your shop as closed. <a href="' . e(path('owner/shop.php')) . '">Open again</a></div></div>';
    }
    $toggle = '<form method="post" action="' . e(path('owner/shop.php')) . '" class="d-none d-sm-block">' . csrf_field() . '<input type="hidden" name="action" value="toggle_open">'
        . '<button class="btn btn-sm ' . ((int) $o['shop']['accepting_orders'] ? 'btn-outline-success' : 'btn-outline-secondary') . '"><i class="bi bi-circle-fill small"></i> ' . ((int) $o['shop']['accepting_orders'] ? 'Open' : 'Paused') . '</button></form>';
    panel_header($title, ['area' => 'owner', 'shop_name' => $o['shop']['name'], 'user_name' => $o['user']['name'], 'user_sub' => '@' . $o['user']['username'] . ' · ' . $o['shop']['shop_code'],
        'notif_url' => 'owner/notifications.php', 'unread' => notifications_for('shop', $sid, 1)['unread'], 'banner' => $banner, 'extra_top' => $toggle,
        'menu' => [
            ['Dashboard', 'owner/', 'grid-1x2'],
            ['Orders', 'owner/orders.php', 'receipt', $new],
            ['—', 'Shop'],
            ['QR code', 'owner/qr.php', 'qr-code'],
            ['Wi-Fi', 'owner/wifi.php', 'wifi'],
            ['Services', 'owner/services.php', 'grid-3x3-gap'],
            ['Pricing', 'owner/pricing.php', 'tags'],
            ['Shop & settings', 'owner/shop.php', 'shop'],
            ['—', 'Business'],
            ['Reports', 'owner/reports.php', 'bar-chart'],
            ['Subscription', 'owner/subscription.php', 'calendar-check'],
        ]]);
    echo '<script>window.PH_NOTIF_FEED = ' . json_encode(path('api/v1/owner/notifications')) . ';</script>';
}

function owner_footer(array $scripts = []): void
{
    panel_footer($scripts);
}
