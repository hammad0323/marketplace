<?php
/**
 * Audit Trail Engine - reads on top of activity_logs (writes happen via log_activity() in auth.php,
 * called from every create/update/delete/login/assignment/settings-change action).
 */
require_once __DIR__ . '/db.php';

function get_activity_logs(?int $companyId, array $filters = [], int $limit = 100, int $offset = 0): array
{
    $where = ['1=1']; $params = [];
    if ($companyId !== null) { $where[] = 'company_id = ?'; $params[] = $companyId; }
    if (!empty($filters['module'])) { $where[] = 'module = ?'; $params[] = $filters['module']; }
    if (!empty($filters['action'])) { $where[] = 'action = ?'; $params[] = $filters['action']; }
    if (!empty($filters['user_id'])) { $where[] = 'user_id = ?'; $params[] = (int)$filters['user_id']; }
    if (!empty($filters['date_from'])) { $where[] = 'created_at >= ?'; $params[] = $filters['date_from'] . ' 00:00:00'; }
    if (!empty($filters['date_to'])) { $where[] = 'created_at <= ?'; $params[] = $filters['date_to'] . ' 23:59:59'; }
    $limit = max(1, min(500, $limit));
    $sql = "SELECT * FROM activity_logs WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT $limit OFFSET " . (int)$offset;
    return db_all($sql, $params);
}

function get_login_activity(?int $companyId, int $limit = 100): array
{
    $where = ["action IN ('login','logout','login_failed')"];
    $params = [];
    if ($companyId !== null) { $where[] = 'company_id = ?'; $params[] = $companyId; }
    $limit = max(1, min(500, $limit));
    $sql = "SELECT * FROM activity_logs WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT $limit";
    return db_all($sql, $params);
}
