<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();
mp_verify_csrf();

$category = mp_find_category((int) ($_POST['id'] ?? 0));
if ($category) {
    $productCount = (int) mp_db_fetch_value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$category['id']]);
    if ($productCount > 0) {
        mp_flash('error', 'Cannot delete a category that still has products in it.');
    } else {
        mp_delete_category($category['id']);
        mp_log_activity('admin', $admin['id'], 'category.deleted', 'category', $category['id'], $category['name']);
        mp_flash('success', $category['name'] . ' deleted.');
    }
}

mp_redirect('categories.php');
