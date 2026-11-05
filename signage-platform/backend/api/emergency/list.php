<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
db_run("UPDATE emergency_messages SET status = 'expired' WHERE status = 'active' AND ends_at IS NOT NULL AND ends_at < UTC_TIMESTAMP()");
ok(shape_rows(db_all('SELECT * FROM emergency_messages WHERE user_id = ? ORDER BY message_id DESC LIMIT 50', [$u['user_id']]), ['style_json', 'target_device_ids_json']));
