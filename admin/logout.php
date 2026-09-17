<?php
require __DIR__ . '/../config.php';
wh_admin_logout();
wh_redirect(BASE_URL . '/admin/login.php');
