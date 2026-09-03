<?php
require __DIR__ . '/../config/config.php';
redirect(base_url('actions/auth.php?do=logout_admin'));
