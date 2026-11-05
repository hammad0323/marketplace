<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
db_run('DELETE FROM notifications WHERE user_id = ? AND notification_id = ?', [$u['user_id'], v_int(input(), 'notification_id', true, 1)]);
ok(null, 'Notification removed');
