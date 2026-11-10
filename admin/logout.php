<?php
define('ADMIN_LOGIN_PAGE', true);
require __DIR__ . '/includes/init.php';
admin_logout();
flash('success', 'You have been logged out.');
redirect('admin/login.php');
