<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_super_admin();
csrf_require();

$action = post('action');
$id = post_int('id');
$company = db_one("SELECT * FROM companies WHERE id=?", [$id]);
if (!$company) json_response(['success' => false, 'message' => 'Company not found.'], 404);

if ($action === 'toggle_status') {
    $status = post('status') === 'active' ? 'active' : 'inactive';
    db_exec("UPDATE companies SET status=? WHERE id=?", [$status, $id]);
    log_activity(null, current_user_id(), 'update', 'company', $id, "Set status to $status");
    json_response(['success' => true]);
}

if ($action === 'delete') {
    db_exec("DELETE FROM companies WHERE id=?", [$id]);
    log_activity(null, current_user_id(), 'delete', 'company', $id, 'Deleted company ' . $company['name']);
    json_response(['success' => true]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);
