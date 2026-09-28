<?php
define('BASE', '../');
require __DIR__ . '/../config.php';
$_SESSION = [];
session_destroy();
redirect('index.php');
