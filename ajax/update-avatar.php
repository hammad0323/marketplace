<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
if (!is_logged_in()) {
    json_response(false, [], 'Please log in.');
}

[$ok, $result] = handle_upload('avatar', 'avatars', ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024);
if (!$ok) {
    json_response(false, [], $result);
}

$stmt = mysqli_prepare(db(), 'UPDATE users SET avatar = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'si', $result, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

json_response(true, ['avatar_url' => UPLOAD_URL . '/' . $result], 'Profile photo updated.');
