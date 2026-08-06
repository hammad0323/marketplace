<?php
/**
 * activity_log — a generic audit trail. Every admin/vendor action that
 * changes platform state (approvals, product/category edits, settings
 * changes, ...) calls mp_log_activity() so admin/activity-log.php has
 * a real record of who did what and when.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_log_activity(
    string $actorType,
    ?int $actorId,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $description = null
): void {
    mp_db_insert('activity_log', [
        'actor_type'  => $actorType,
        'actor_id'    => $actorId,
        'action'      => $action,
        'entity_type' => $entityType,
        'entity_id'   => $entityId,
        'description' => $description,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function mp_recent_activity(int $limit = 50): array
{
    return mp_db_fetch_all('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?', [$limit]);
}

function mp_activity_for_entity(string $entityType, int $entityId): array
{
    return mp_db_fetch_all(
        'SELECT * FROM activity_log WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC',
        [$entityType, $entityId]
    );
}

function mp_activity_count(): int
{
    return (int) mp_db_fetch_value('SELECT COUNT(*) FROM activity_log');
}
