<?php
require __DIR__ . '/../config/config.php';
unset($_SESSION['customer_id']);
mp_redirect(ROUTE_HOME);
