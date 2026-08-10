<?php
require_once __DIR__ . '/../config/config.php';
logout_user($conn);
redirect('/admin/login.php');
