<?php
require __DIR__ . '/_init.php';
if (is_post()) {
    $_SESSION = [];
    session_destroy();
}
redirect('admin/login.php');
