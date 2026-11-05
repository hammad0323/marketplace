<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
if (v_bool($in, 'all', false)) {
    $n = db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$u['user_id']]);
} else {
    $ids = isset($in['notification_ids']) ? v_int_list($in, 'notification_ids', true) : [v_int($in, 'notification_id', true, 1)];
    $n = db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND notification_id IN (' . placeholders($ids) . ')', array_merge([$u['user_id']], $ids));
}
ok(['updated' => $n], 'Marked as read');
