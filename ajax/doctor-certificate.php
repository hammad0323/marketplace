<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$db = db();
$doctorId = current_profile_id();
$op = $_POST['op'] ?? 'add';

if ($op === 'remove') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($db, 'SELECT file_path FROM doctor_certificates WHERE id = ? AND doctor_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $id, $doctorId);
    mysqli_stmt_execute($stmt);
    $cert = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if ($cert) {
        $stmt = mysqli_prepare($db, 'DELETE FROM doctor_certificates WHERE id = ? AND doctor_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $id, $doctorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $fullPath = UPLOAD_PATH . '/' . $cert['file_path'];
        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
    json_response(true, [], 'Certificate removed.');
}

$title = clean($_POST['title'] ?? '');
$issuedBy = clean($_POST['issued_by'] ?? '');
$issuedYear = (int) ($_POST['issued_year'] ?? 0) ?: null;

if ($title === '') {
    json_response(false, ['errors' => ['title' => 'Certificate title is required.']], 'Please fix the errors below.');
}

[$ok, $result] = handle_upload('file', 'certificates', ['jpg', 'jpeg', 'png', 'pdf'], 5 * 1024 * 1024);
if (!$ok) {
    json_response(false, [], $result);
}

$stmt = mysqli_prepare($db, 'INSERT INTO doctor_certificates (doctor_id, title, issued_by, issued_year, file_path) VALUES (?, ?, ?, ?, ?)');
mysqli_stmt_bind_param($stmt, 'issis', $doctorId, $title, $issuedBy, $issuedYear, $result);
mysqli_stmt_execute($stmt);
$id = mysqli_insert_id($db);
mysqli_stmt_close($stmt);

json_response(true, ['id' => $id], 'Certificate uploaded.');
