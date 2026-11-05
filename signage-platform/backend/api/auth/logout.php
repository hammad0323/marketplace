<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_id = ?', [$u['token_id']]);
log_activity((int)$u['user_id'], 'auth.logout', 'user', (int)$u['user_id'], 'Signed out');
ok(null, 'Signed out');
