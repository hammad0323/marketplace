<?php
/**
 * In-app notification centre (always works). The owner app and the web dashboards poll it;
 * the browser shows a system notification when the user allowed it, the Flutter app a local notification.
 * recipient_type: admin (recipient_id NULL = all admins), shop (recipient_id = shop id), customer (recipient_id = customer session id).
 */

function notify(string $type, ?int $recipientId, ?int $shopId, ?int $orderId, string $kind, string $title, ?string $body = null, ?string $link = null): void
{
    db_insert('notifications', ['recipient_type' => $type, 'recipient_id' => $recipientId, 'shop_id' => $shopId, 'order_id' => $orderId,
        'type' => $kind, 'title' => mb_substr($title, 0, 160), 'body' => $body !== null ? mb_substr($body, 0, 500) : null, 'link' => $link]);
}

function notifications_for(string $type, ?int $recipientId, int $limit = 30, int $sinceId = 0): array
{
    $where = $type === 'admin' ? "recipient_type = 'admin' AND (recipient_id IS NULL OR recipient_id = ?)" : 'recipient_type = ? AND recipient_id = ?';
    $params = $type === 'admin' ? [(int) $recipientId] : [$type, (int) $recipientId];
    $rows = db_all("SELECT id, type, title, body, link, order_id, is_read, created_at FROM notifications WHERE $where AND id > ? ORDER BY id DESC LIMIT " . (int) $limit, array_merge($params, [$sinceId]));
    $unread = (int) db_val("SELECT COUNT(*) FROM notifications WHERE $where AND is_read = 0", $params);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['is_read'] = (bool) $r['is_read'];
        $r['order_id'] = $r['order_id'] !== null ? (int) $r['order_id'] : null;
        $r['link'] = $r['link'] ? url($r['link']) : null;
    }
    return ['items' => $rows, 'unread' => $unread];
}

function notifications_mark_read(string $type, ?int $recipientId, ?int $id = null): void
{
    $where = $type === 'admin' ? "recipient_type = 'admin' AND (recipient_id IS NULL OR recipient_id = ?)" : 'recipient_type = ? AND recipient_id = ?';
    $params = $type === 'admin' ? [(int) $recipientId] : [$type, (int) $recipientId];
    if ($id) {
        $where .= ' AND id = ?';
        $params[] = $id;
    }
    db_exec("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE $where AND is_read = 0", $params);
}
