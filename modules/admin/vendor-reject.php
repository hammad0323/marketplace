<?php
require_admin();
verify_csrf();

$vendor = find_vendor((int) $id);
$reason = trim($_POST['reason'] ?? 'Not specified');

if ($vendor) {
    reject_vendor($vendor['id'], $reason);
    notify('vendor.rejected', $vendor['email'], ['store_name' => $vendor['store_name'], 'reason' => $reason]);
    flash('success', $vendor['store_name'] . ' rejected.');
}

redirect('/admin/vendors');
