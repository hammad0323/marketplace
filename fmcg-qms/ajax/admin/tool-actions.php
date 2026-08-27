<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_super_admin();
csrf_require();

$action = post('action');
$id = post_int('id');

if ($action === 'toggle_status') {
    $status = post('status') === 'active' ? 'active' : 'inactive';
    db_exec("UPDATE tools SET status=? WHERE id=?", [$status, $id]);
    log_activity(null, current_user_id(), 'update', 'tool', $id, "Set tool status to $status");
    json_response(['success' => true]);
}

json_response(['success' => false, 'message' => 'Unknown action.'], 400);
