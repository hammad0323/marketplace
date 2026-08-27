<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_manager();
csrf_require();
$cid = require_company_id();

$id = post_int('id');
$name = post('name');
$description = post('description');
$status = post('status', 'active');
if (!$name) json_response(['success' => false, 'message' => 'Name is required.'], 422);

if ($id) {
    $existing = db_one("SELECT id FROM departments WHERE id=? AND company_id=?", [$id, $cid]);
    if (!$existing) json_response(['success' => false, 'message' => 'Department not found.'], 404);
    db_exec("UPDATE departments SET name=?, description=?, status=? WHERE id=? AND company_id=?", [$name, $description, $status, $id, $cid]);
} else {
    $limit = check_company_limit($cid, 'department_limit', 'departments');
    if (!$limit['allowed']) json_response(['success' => false, 'message' => 'Department limit reached for your plan.'], 422);
    $id = db_exec("INSERT INTO departments (company_id, name, description, status) VALUES (?,?,?,?)", [$cid, $name, $description, $status]);
}
log_activity($cid, current_user_id(), $id ? 'update' : 'create', 'department', $id, "Saved department $name");
json_response(['success' => true]);
