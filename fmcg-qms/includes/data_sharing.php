<?php
/**
 * Cross-Department Data Sharing Engine.
 * A department always sees its own data; department_data_shares additionally grants a
 * department read-only visibility into another department's quality data. Never grants
 * write access - enforced by only ever exposing shared data through read-only views.
 */
require_once __DIR__ . '/db.php';

function add_department_share(int $companyId, int $userId, int $viewerDeptId, int $sourceDeptId): bool
{
    if ($viewerDeptId === $sourceDeptId) {
        return false; // a department always sees its own data; no share needed
    }
    $ok = db_exec(
        "INSERT IGNORE INTO department_data_shares (company_id, viewer_department_id, source_department_id, created_by) VALUES (?,?,?,?)",
        [$companyId, $viewerDeptId, $sourceDeptId, $userId]
    ) !== false;
    if ($ok) {
        log_activity($companyId, $userId, 'create', 'department_data_share', null, "Granted department #$viewerDeptId read-only access to department #$sourceDeptId data");
    }
    return $ok;
}

function remove_department_share(int $shareId, int $companyId, int $userId): bool
{
    $ok = db_exec("DELETE FROM department_data_shares WHERE id=? AND company_id=?", [$shareId, $companyId]) !== false;
    if ($ok) {
        log_activity($companyId, $userId, 'delete', 'department_data_share', $shareId, 'Removed department data share');
    }
    return $ok;
}

function get_department_shares(int $companyId): array
{
    return db_all(
        "SELECT dds.*, vd.name AS viewer_department_name, sd.name AS source_department_name
         FROM department_data_shares dds
         JOIN departments vd ON vd.id = dds.viewer_department_id
         JOIN departments sd ON sd.id = dds.source_department_id
         WHERE dds.company_id = ? ORDER BY vd.name, sd.name",
        [$companyId]
    );
}

/** Departments (other than the employee's own) whose data has been shared with the given department. */
function get_departments_shared_with(int $companyId, int $viewerDeptId): array
{
    return db_all(
        "SELECT sd.* FROM department_data_shares dds JOIN departments sd ON sd.id = dds.source_department_id
         WHERE dds.company_id = ? AND dds.viewer_department_id = ? AND sd.status = 'active' ORDER BY sd.name",
        [$companyId, $viewerDeptId]
    );
}

function department_can_view(int $companyId, ?int $viewerDeptId, ?int $sourceDeptId): bool
{
    if ($viewerDeptId === null || $sourceDeptId === null) {
        return false;
    }
    if ($viewerDeptId === $sourceDeptId) {
        return true;
    }
    $row = db_one(
        "SELECT id FROM department_data_shares WHERE company_id=? AND viewer_department_id=? AND source_department_id=?",
        [$companyId, $viewerDeptId, $sourceDeptId]
    );
    return $row !== null;
}
