<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
if (request_method() === 'POST') {
    $id = v_int(input(), 'token_id', true, 1);
    db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE token_id = ? AND user_id = ?', [$id, $u['user_id']]);
    ok(null, 'Session signed out');
}
$rows = db_all('SELECT token_id, device_label, ip_address, user_agent, last_used_at, expires_at, created_at FROM api_tokens
                 WHERE user_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY created_at DESC', [$u['user_id']]);
foreach ($rows as &$r) $r['is_current'] = (int)$r['token_id'] === (int)$u['token_id'];
ok($rows);
