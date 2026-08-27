<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_manager();
csrf_require();
$cid = require_company_id();

$action = post('action');
$userId = post_int('user_id');
$toolId = post_int('tool_id');

$employee = db_one("SELECT id FROM users WHERE id=? AND company_id=? AND role='employee'", [$userId, $cid]);
if (!$employee) json_response(['success' => false, 'message' => 'Employee not found.'], 404);

if ($action === 'assign') {
    $limit = check_company_limit($cid, 'tool_limit', 'tool_assignments', "status='active'");
    if (!$limit['allowed']) json_response(['success' => false, 'message' => 'Tool limit reached for your plan.'], 422);
    $assignmentId = assign_tool_to_user($cid, $toolId, $userId, current_user_id());
    $tool = db_one("SELECT name FROM tools WHERE id=?", [$toolId]);
    $emp = db_one("SELECT name, email FROM users WHERE id=?", [$userId]);
    notify($cid, $userId, 'tool_assigned', 'New Tool Assigned', ($tool['name'] ?? 'A tool') . ' has been assigned to you.', base_url('employee/dashboard.php'));
    if ($emp) {
        send_event_email($cid, $emp['email'], $emp['name'], 'tool_assigned', ['employee_name' => $emp['name'], 'tool_name' => $tool['name'] ?? '']);
    }
    log_activity($cid, current_user_id(), 'create', 'tool_assignment', $assignmentId, "Assigned tool #$toolId to user #$userId");
    json_response(['success' => true]);
}

if ($action === 'unassign') {
    db_exec("UPDATE tool_assignments SET status='removed' WHERE company_id=? AND tool_id=? AND user_id=?", [$cid, $toolId, $userId]);
    log_activity($cid, current_user_id(), 'update', 'tool_assignment', $toolId, "Removed tool #$toolId from user #$userId");
    json_response(['success' => true]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);
