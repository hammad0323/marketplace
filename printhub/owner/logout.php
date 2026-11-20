<?php
require __DIR__ . '/_init.php';
if (is_post() && $OWNER) {
    token_revoke($OWNER['token']);
    clear_cookie('ph_owner');
    $_SESSION = [];
    session_destroy();
}
redirect('owner/login.php');
