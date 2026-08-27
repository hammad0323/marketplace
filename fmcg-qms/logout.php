<?php
require_once __DIR__ . '/includes/bootstrap.php';
do_logout();
redirect(base_url('login.php'));
