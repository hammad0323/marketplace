<?php
$admin = mp_require_admin();
mp_verify_csrf();

$vendor = mp_find_vendor((int) $id);
if ($vendor) {
    mp_approve_vendor($vendor['id'], $admin['id']);
    mp_notify('vendor.approved', $vendor['email'], ['store_name' => $vendor['store_name']]);
    mp_flash('success', $vendor['store_name'] . ' approved. Store is now public.');
}

mp_redirect('/admin/vendors');
