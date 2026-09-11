<?php
require __DIR__ . '/../config/config.php';
require_role_page_or_json('doctor');

$q = clean($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    json_response(true, ['patients' => []]);
}

$like = '%' . $q . '%';
$stmt = mysqli_prepare(db(), 'SELECT p.id, u.full_name, u.email, u.phone FROM patients p JOIN users u ON u.id = p.user_id
    WHERE u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? ORDER BY u.full_name LIMIT 10');
mysqli_stmt_bind_param($stmt, 'sss', $like, $like, $like);
mysqli_stmt_execute($stmt);
$patients = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

json_response(true, ['patients' => $patients]);
