<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

$status = $_POST['decision'] ?? '';
if (!in_array($status, ['approved', 'rejected'], true)) {
    mp_redirect('category-requests.php');
}

$notes = trim($_POST['notes'] ?? '') ?: null;
$usageLimit = !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null;

mp_decide_category_request((int) ($_GET['id'] ?? 0), $status, $admin['id'], $notes, $usageLimit);

mp_flash('success', 'Category request updated.');
mp_redirect('category-requests.php');
