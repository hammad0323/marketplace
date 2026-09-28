<?php
require dirname(__DIR__) . '/config.php';
unset($_SESSION['admin_id']);
session_regenerate_id(true);
redirect('admin/login.php');
