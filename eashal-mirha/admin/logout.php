<?php
define('ADMIN_PUBLIC', true);
require __DIR__ . '/includes/admin.php';

unset($_SESSION['admin_id']);
session_regenerate_id(true);
flash('success', 'You have been logged out.');
redirect('admin/login');
