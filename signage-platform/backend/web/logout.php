<?php
require __DIR__ . '/_bootstrap.php';
if (!empty($_SESSION['api_token'])) db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_hash = ?', [sha256($_SESSION['api_token'])]);
$_SESSION = [];
session_destroy();
header('Location: ' . web_url('login.php'));
