<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

$category = mp_find_category((int) ($_POST['id'] ?? 0));
if ($category) {
    mp_set_category_active($category['id'], !$category['is_active']);
    mp_log_activity('admin', $admin['id'], 'category.toggled', 'category', $category['id']);
}

mp_redirect('categories.php');
