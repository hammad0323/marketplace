<?php
require __DIR__ . '/../config/config.php';

$platformAdmin = mp_require_platform_admin();
mp_verify_csrf();

$tenant = mp_find_tenant((int) ($_POST['id'] ?? 0));

if ($tenant) {
    mp_activate_tenant($tenant['id']);
    mp_log_activity('platform_admin', $platformAdmin['id'], 'tenant.activated', 'tenant', $tenant['id']);
    mp_flash('success', $tenant['business_name'] . ' has been reactivated.');
}

mp_redirect('tenant-view.php?id=' . ($tenant['id'] ?? 0));
