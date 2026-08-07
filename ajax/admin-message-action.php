<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$id = (int) ($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
if (!in_array($status, ['new', 'read', 'replied'], true)) {
    json_response(false, [], 'Invalid status.');
}

$stmt = mysqli_prepare(db(), 'UPDATE contact_messages SET status = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $status, $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

json_response(true, [], 'Message updated.');
