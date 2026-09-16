<?php
require_once __DIR__ . '/includes/functions.php';
$q = $_GET['q'] ?? '';
redirect(url('shop', ['q' => $q]));
