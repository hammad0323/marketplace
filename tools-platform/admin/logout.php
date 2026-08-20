<?php
require __DIR__ . '/../includes/config.php';
tp_admin_logout();
header('Location: ' . tp_url('admin/login.php'));
