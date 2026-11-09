<?php
require __DIR__ . '/_bootstrap.php';
$u = web_user();
header('Location: ' . web_url(!$u ? 'login.php' : ($u['role_key'] === ROLE_SUPER_ADMIN ? 'admin/index.php' : 'dashboard.php')));
