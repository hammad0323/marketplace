<?php
require_once __DIR__ . '/includes/functions.php';
$q = $_GET['q'] ?? '';
redirect(BASE_URL . '/shop.php?q=' . urlencode($q));
