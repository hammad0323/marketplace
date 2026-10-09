<?php
require __DIR__ . '/_inc/bootstrap.php';
if (is_post()) {
    require_csrf();
    admin_logout();
}
redirect(admin_url('login'));
