<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$fields = ['show_certificates', 'show_fees', 'show_availability', 'show_clinic_address', 'show_phone', 'show_email', 'show_free_consultation', 'show_store', 'show_reviews'];
$values = [];
foreach ($fields as $f) {
    $values[$f] = !empty($_POST[$f]) ? 1 : 0;
}

$sql = 'UPDATE doctor_privacy_settings SET ' . implode(', ', array_map(fn($f) => "$f = ?", $fields)) . ' WHERE doctor_id = ?';
$stmt = mysqli_prepare(db(), $sql);
$types = str_repeat('i', count($fields)) . 'i';
$params = array_merge(array_values($values), [$doctorId]);
$bindArgs = [$stmt, $types];
foreach ($params as $key => $value) {
    $bindArgs[] = &$params[$key];
}
call_user_func_array('mysqli_stmt_bind_param', $bindArgs);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

json_response(true, [], 'Privacy settings updated.');
