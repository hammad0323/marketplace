<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_super_admin();
csrf_require();

$id = post_int('id');
$data = [
    'name' => post('name'), 'price_monthly' => post_float('price_monthly'), 'status' => post('status', 'active'),
    'employee_limit' => post_int('employee_limit'), 'department_limit' => post_int('department_limit'),
    'tool_limit' => post_int('tool_limit'), 'storage_limit_mb' => post_int('storage_limit_mb'),
    'ai_usage_limit' => post_int('ai_usage_limit'), 'report_limit' => post_int('report_limit'),
    'features' => post('features'),
];
if (!$data['name']) json_response(['success' => false, 'message' => 'Plan name is required.'], 422);

if ($id) {
    db_exec("UPDATE subscription_plans SET name=?,price_monthly=?,status=?,employee_limit=?,department_limit=?,tool_limit=?,storage_limit_mb=?,ai_usage_limit=?,report_limit=?,features=? WHERE id=?",
        [...array_values($data), $id]);
    log_activity(null, current_user_id(), 'update', 'subscription_plan', $id, 'Updated plan ' . $data['name']);
} else {
    $id = db_exec("INSERT INTO subscription_plans (name,price_monthly,status,employee_limit,department_limit,tool_limit,storage_limit_mb,ai_usage_limit,report_limit,features)
        VALUES (?,?,?,?,?,?,?,?,?,?)", array_values($data));
    log_activity(null, current_user_id(), 'create', 'subscription_plan', $id, 'Created plan ' . $data['name']);
}

json_response(['success' => true]);
