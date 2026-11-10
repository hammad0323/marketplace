<?php
require dirname(__DIR__) . '/includes/init.php';
if (is_post()) {
    csrf_check();
    db_exec('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
    json_out(['ok' => true]);
}
$items = db_all('SELECT id, type, title, message, link, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 12');
foreach ($items as &$n) $n['ago'] = time_ago($n['created_at']);
json_out(['ok' => true, 'unread' => (int) db_val('SELECT COUNT(*) FROM notifications WHERE is_read = 0'), 'items' => $items]);
