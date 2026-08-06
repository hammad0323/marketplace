<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

if ($admin['role'] !== 'super_admin') {
    mp_flash('error', 'Only a super admin can change admin account status.');
    mp_redirect('admin-users.php');
}

$target = mp_find_admin((int) ($_POST['id'] ?? 0));
if ($target && (int) $target['id'] !== (int) $admin['id']) {
    mp_set_admin_active($target['id'], !$target['is_active']);
    mp_log_activity('admin', $admin['id'], 'admin.toggled', 'admin', $target['id']);
}

mp_redirect('admin-users.php');
