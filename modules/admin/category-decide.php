<?php
$admin = require_admin();
verify_csrf();

$status = $_POST['decision'] ?? '';
if (!in_array($status, ['approved', 'rejected'], true)) {
    redirect('/admin/category-requests');
}

$notes = trim($_POST['notes'] ?? '') ?: null;
$usageLimit = !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null;

decide_category_request((int) $id, $status, $admin['id'], $notes, $usageLimit);

flash('success', 'Category request updated.');
redirect('/admin/category-requests');
