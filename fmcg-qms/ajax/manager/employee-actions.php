<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_manager();
csrf_require();
$cid = require_company_id();

$action = post('action');
$id = post_int('id');
$employee = db_one("SELECT * FROM users WHERE id=? AND company_id=? AND role='employee'", [$id, $cid]);
if (!$employee) json_response(['success' => false, 'message' => 'Employee not found.'], 404);

if ($action === 'toggle_status') {
    $status = post('status') === 'active' ? 'active' : 'inactive';
    db_exec("UPDATE users SET status=? WHERE id=? AND company_id=?", [$status, $id, $cid]);
    log_activity($cid, current_user_id(), 'update', 'employee', $id, "Set status to $status");
    json_response(['success' => true]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);
