<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$id = (int) ($_POST['id'] ?? 0);

$stmt = mysqli_prepare($db, 'SELECT name, featured_image FROM medicine_info WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$medicine = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$medicine) {
    json_response(false, [], 'Medicine entry not found.');
}

$stmt = mysqli_prepare($db, 'DELETE FROM medicine_info WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($medicine['featured_image']) {
    $fullPath = UPLOAD_PATH . '/' . $medicine['featured_image'];
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

log_activity((int) $_SESSION['user_id'], 'admin', 'delete_medicine_info', "Deleted medicine info: {$medicine['name']}");
json_response(true, [], 'Medicine entry deleted.');
