<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

$banner = mp_find_banner((int) ($_POST['id'] ?? 0));
if ($banner) {
    mp_delete_banner($banner['id']);
    mp_log_activity('admin', $admin['id'], 'banner.deleted', 'banner', $banner['id'], $banner['title']);
    mp_flash('success', 'Banner deleted.');
}

mp_redirect('banners.php');
