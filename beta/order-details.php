<?php
require __DIR__ . '/config/config.php';
redirect(customer_url('order-details.php?id=' . (int)($_GET['id'] ?? 0)));
