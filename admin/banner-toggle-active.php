<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

$banner = mp_find_banner((int) ($_POST['id'] ?? 0));
if ($banner) {
    mp_update_banner($banner['id'], ['is_active' => $banner['is_active'] ? 0 : 1]);
    mp_log_activity('admin', $admin['id'], 'banner.toggled', 'banner', $banner['id']);
}

mp_redirect('banners.php');
