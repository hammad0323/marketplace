<?php
require __DIR__ . '/../config/config.php';

$platformAdmin = mp_require_platform_admin();
mp_verify_csrf();

$tenant = mp_find_tenant((int) ($_POST['id'] ?? 0));
$reason = trim($_POST['reason'] ?? '');

if ($tenant && $reason !== '') {
    mp_suspend_tenant($tenant['id'], $reason);
    mp_log_activity('platform_admin', $platformAdmin['id'], 'tenant.suspended', 'tenant', $tenant['id'], $reason);
    mp_flash('success', $tenant['business_name'] . ' has been suspended.');
}

mp_redirect('tenant-view.php?id=' . ($tenant['id'] ?? 0));
