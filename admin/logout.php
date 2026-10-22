<?php
require __DIR__ . '/includes/init.php';
if (is_post()) {
    csrf_check();
    admin_logout();
}
redirect('admin/login.php');
