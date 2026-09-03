<?php
require __DIR__ . '/../../config/config.php';
require_admin();
csrf_verify();

$id = (int)$_POST['id'];
$action = $_POST['action'];
$shop = db_fetch_one("SELECT * FROM shops WHERE id=?", 'i', [$id]);
if (!$shop) { flash('error', 'Shop not found.'); redirect(admin_url('shops/index.php')); }
$admin = current_admin();

if ($action === 'approve') {
    db_exec("UPDATE shops SET status='active' WHERE id=?", 'i', [$id]);
    notify('shop_owner', $shop['owner_id'], 'Shop Approved', "Your shop {$shop['shop_name']} has been approved!", 'shop/dashboard.php');
    audit_log('admin', $admin['id'], $admin['name'], 'Approved shop', 'shops', $id, $shop['shop_name']);
    flash('success', 'Shop approved.');
} elseif ($action === 'reject') {
    $reason = trim($_POST['reason'] ?? 'Application did not meet requirements.');
    db_exec("UPDATE shops SET status='inactive', rejection_reason=? WHERE id=?", 'si', [$reason, $id]);
    notify('shop_owner', $shop['owner_id'], 'Shop Application Rejected', "Reason: $reason", 'shop/login.php');
    audit_log('admin', $admin['id'], $admin['name'], 'Rejected shop', 'shops', $id, $reason);
    flash('success', 'Shop rejected.');
} elseif ($action === 'set_status') {
    $status = $_POST['status'];
    db_exec("UPDATE shops SET status=?, status_reason=NULL WHERE id=?", 'si', [$status, $id]);
    notify('shop_owner', $shop['owner_id'], 'Shop Status Updated', "Your shop status is now: $status", 'shop/dashboard.php');
    audit_log('admin', $admin['id'], $admin['name'], 'Changed shop status to ' . $status, 'shops', $id);
    flash('success', 'Shop status updated.');
}
redirect(admin_url('shops/index.php'));
