<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role(['manager', 'employee']);

$action = post('action', get_param('action', 'list'));

if ($action === 'mark_all_read') {
    csrf_require();
    mark_all_notifications_read(current_user_id());
    json_response(['success' => true]);
}

if ($action === 'mark_read') {
    csrf_require();
    mark_notification_read(current_user_id(), post_int('id'));
    json_response(['success' => true]);
}

$rows = get_recent_notifications(current_user_id(), 15);
$items = array_map(function ($n) {
    return [
        'id' => (int)$n['id'], 'title' => $n['title'], 'message' => $n['message'], 'link' => $n['link'] ?: '#',
        'severity' => $n['severity'], 'is_read' => (int)$n['is_read'], 'time_ago' => time_ago($n['created_at']),
    ];
}, $rows);

json_response(['success' => true, 'unread' => get_unread_count(current_user_id()), 'items' => $items]);
