<?php
/**
 * Notification Engine - bell notifications + dashboard alerts.
 */
require_once __DIR__ . '/db.php';

function notify(int $companyId, ?int $userId, string $type, string $title, string $message, string $link = '', string $severity = 'info'): int
{
    return (int)db_execute(
        "INSERT INTO notifications (company_id, user_id, type, title, message, link, severity, is_read, created_at)
         VALUES (?,?,?,?,?,?,?,0,NOW())",
        'iisssss',
        [$companyId, $userId, $type, $title, $message, $link, $severity]
    );
}

/**
 * Notify all managers of a company (user_id NULL row visible to any manager query, or fan-out).
 */
function notify_company_managers(int $companyId, string $type, string $title, string $message, string $link = '', string $severity = 'info'): void
{
    $managers = db_fetch_all("SELECT id FROM users WHERE company_id = ? AND role = 'manager' AND status='active'", 'i', [$companyId]);
    foreach ($managers as $m) {
        notify($companyId, (int)$m['id'], $type, $title, $message, $link, $severity);
    }
}

function get_unread_count(int $userId): int
{
    return db_count('notifications', 'user_id = ? AND is_read = 0', 'i', [$userId]);
}

function get_recent_notifications(int $userId, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));
    return db_fetch_all(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit",
        'i',
        [$userId]
    );
}

function mark_notification_read(int $userId, int $notificationId): bool
{
    return db_execute("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", 'ii', [$notificationId, $userId]) !== false;
}

function mark_all_notifications_read(int $userId): bool
{
    return db_execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", 'i', [$userId]) !== false;
}
