<?php
require __DIR__ . '/partials/bootstrap.php';
if (is_post()) {
    csrf_check();
    if (admin_id()) audit('logout', 'admin', admin_id());
    unset($_SESSION['admin_id'], $_SESSION['admin_seen'], $_SESSION['preview_mode']);
    session_regenerate_id(true);
}
redirect(admin_url('login'));
