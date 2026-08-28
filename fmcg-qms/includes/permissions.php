<?php
/**
 * Permission Engine - role-based + company-scoped + granular tool access.
 */
require_once __DIR__ . '/auth.php';

function require_login(): void
{
    if (!is_logged_in()) {
        $target = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . base_url('login.php') . '?redirect=' . urlencode($target));
        exit;
    }
}

function require_role(array $roles): void
{
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}

function require_super_admin(): void
{
    require_role(['super_admin']);
}

function require_manager(): void
{
    require_role(['manager']);
}

function require_company_user(): void
{
    require_role(['manager', 'employee']);
}

function require_company_id(): int
{
    $cid = current_company_id();
    if (!$cid) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
    return $cid;
}

/**
 * Ensures a record identified by $recordCompanyId belongs to the logged-in company.
 * Super admins bypass this check only when explicitly viewing a company (handled separately).
 */
function assert_company_owns(?int $recordCompanyId): void
{
    if ($recordCompanyId === null || $recordCompanyId !== current_company_id()) {
        http_response_code(403);
        if (is_ajax_request()) {
            json_response(['success' => false, 'message' => 'Access denied.'], 403);
        }
        include __DIR__ . '/../403.php';
        exit;
    }
}

/**
 * Employee-level granular permission check against user_permissions.
 * Managers implicitly have all company permissions.
 */
function user_has_permission(int $userId, string $permissionKey): bool
{
    if (current_role() === 'manager') {
        return true;
    }
    $row = db_fetch_one(
        "SELECT up.id FROM user_permissions up
         JOIN permissions p ON p.id = up.permission_id
         WHERE up.user_id = ? AND p.permission_key = ? LIMIT 1",
        'is',
        [$userId, $permissionKey]
    );
    return $row !== null;
}

function employee_can_use_tool(int $userId, int $toolId): bool
{
    if (current_role() === 'manager') {
        return true;
    }
    $row = db_fetch_one(
        "SELECT id FROM tool_assignments WHERE user_id = ? AND tool_id = ? AND status = 'active' LIMIT 1",
        'ii',
        [$userId, $toolId]
    );
    return $row !== null;
}

function check_company_limit(int $companyId, string $limitField, string $countTable, string $countWhere = ''): array
{
    $company = db_fetch_one("SELECT $limitField AS lim FROM companies WHERE id = ?", 'i', [$companyId]);
    $limit = $company ? (int)$company['lim'] : 0;
    $where = "company_id = ?" . ($countWhere ? " AND $countWhere" : '');
    $current = db_count($countTable, $where, 'i', [$companyId]);
    return ['limit' => $limit, 'current' => $current, 'allowed' => $limit === 0 || $current < $limit];
}
