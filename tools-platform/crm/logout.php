<?php
require __DIR__ . '/../includes/config.php';
tp_business_logout();
header('Location: ' . tp_url('crm/login.php'));
