<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$doctorId = (int) ($_POST['doctor_id'] ?? 0);
$metaTitle = mb_substr(clean($_POST['meta_title'] ?? ''), 0, 200) ?: null;
$metaDescription = mb_substr(clean($_POST['meta_description'] ?? ''), 0, 300) ?: null;

$stmt = mysqli_prepare(db(), 'SELECT 1 FROM doctors WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$exists = (bool) mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
if (!$exists) {
    json_response(false, [], 'Doctor not found.');
}

$stmt = mysqli_prepare(db(), 'UPDATE doctors SET meta_title = ?, meta_description = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ssi', $metaTitle, $metaDescription, $doctorId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'admin', 'update_doctor_seo', "Updated SEO meta for doctor #$doctorId");
json_response(true, [], 'SEO settings saved.');
