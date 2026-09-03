<?php
require __DIR__ . '/config.php';
unset($_SESSION['customer_id']);
mp_redirect('/');
