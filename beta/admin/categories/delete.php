<?php
require __DIR__ . '/../../config/config.php';
require_admin();
csrf_verify();
$id = (int)$_POST['id'];
db_exec("DELETE FROM categories WHERE id=?", 'i', [$id]);
flash('success', 'Category deleted.');
redirect(admin_url('categories/index.php'));
