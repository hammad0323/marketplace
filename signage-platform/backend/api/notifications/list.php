<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
[$page, $per, $off] = pagination();
$unreadOnly = v_bool(input(), 'unread', false);
$w = 'user_id = ?' . ($unreadOnly ? ' AND is_read = 0' : '');
$total = (int)db_val("SELECT COUNT(*) FROM notifications WHERE {$w}", [$u['user_id']]);
$rows = db_all("SELECT * FROM notifications WHERE {$w} ORDER BY notification_id DESC LIMIT ? OFFSET ?", [$u['user_id'], $per, $off]);
foreach ($rows as &$r) $r['is_read'] = (bool)$r['is_read'];
paged($rows, $total, $page, $per);
