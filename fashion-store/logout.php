<?php
require_once __DIR__ . '/includes/functions.php';
unset($_SESSION['customer_id']);
session_regenerate_id(true);
redirect(BASE_URL . '/index.php');
