<?php
$admin = require_admin();
verify_csrf();

$vendor = find_vendor((int) $id);
if ($vendor) {
    approve_vendor($vendor['id'], $admin['id']);
    notify('vendor.approved', $vendor['email'], ['store_name' => $vendor['store_name']]);
    flash('success', $vendor['store_name'] . ' approved. Store is now public.');
}

redirect('/admin/vendors');
