<?php
/** Super Admin panel bootstrap: session, sign-in guard, CSRF on every POST, layout. */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === '_init.php') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/core/bootstrap.php';
require PH_ROOT . '/core/panel.php';
admin_session_start();
send_security_headers();
header('Cache-Control: no-store');
if (is_post()) {
    csrf_check();
}
$PUBLIC_PAGES = ['login.php'];
if (!in_array(basename($_SERVER['SCRIPT_NAME']), $PUBLIC_PAGES, true)) {
    if (!current_admin()) {
        redirect('admin/login.php');
    }
    if ((int) current_admin()['must_change_password'] && basename($_SERVER['SCRIPT_NAME']) !== 'password.php' && basename($_SERVER['SCRIPT_NAME']) !== 'logout.php') {
        flash('warning', 'Please set a new password before continuing.');
        redirect('admin/password.php');
    }
}

function admin_header(string $title): void
{
    $a = current_admin();
    $pending = (int) db_val("SELECT COUNT(*) FROM subscription_payments WHERE status = 'pending'");
    $unread = notifications_for('admin', (int) $a['id'], 1)['unread'];
    panel_header($title, ['area' => 'admin', 'user_name' => $a['name'], 'user_sub' => $a['email'] . ' · ' . str_replace('_', ' ', $a['role']), 'notif_url' => 'admin/notifications.php', 'unread' => $unread,
        'menu' => [
            ['Dashboard', 'admin/', 'grid-1x2'],
            ['—', 'Shops'],
            ['Print shops', 'admin/shops.php', 'shop'],
            ['Add shop', 'admin/shop-form.php', 'plus-circle'],
            ['Subscriptions', 'admin/subscriptions.php', 'calendar-check'],
            ['Payments', 'admin/payments.php', 'cash-coin', $pending],
            ['—', 'Platform'],
            ['Orders', 'admin/orders.php', 'receipt'],
            ['Categories', 'admin/categories.php', 'grid-3x3-gap'],
            ['Printing options', 'admin/options.php', 'sliders'],
            ['CV templates', 'admin/cv-templates.php', 'file-person'],
            ['—', 'System'],
            ['Settings', 'admin/settings.php', 'gear'],
            ['Activity log', 'admin/activity.php', 'clock-history'],
            ['Admin users', 'admin/admins.php', 'people'],
        ]]);
    echo '<script>window.PH_NOTIF_FEED = ' . json_encode(path('admin/notifications.php?feed=1')) . ';</script>';
}

function admin_footer(array $scripts = []): void
{
    panel_footer($scripts);
}

function require_super_admin(): void
{
    if (current_admin()['role'] !== 'super_admin') {
        flash('error', 'Only a Super Admin can do that.');
        redirect('admin/');
    }
}
