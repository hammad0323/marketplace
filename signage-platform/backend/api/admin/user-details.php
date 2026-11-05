<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_admin();
ok(admin_present_user(admin_user_row(v_int(input(), 'user_id', true, 1)), true));
