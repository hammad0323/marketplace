<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
$name = v_str($in, 'full_name', false, 150, 2) ?? $u['full_name'];
$phone = array_key_exists('phone', $in) ? v_str($in, 'phone', false, 30) : $u['phone'];
$biz = array_key_exists('business_name', $in) ? v_str($in, 'business_name', false, 150) : $u['business_name'];
$tz = v_timezone($in, 'timezone') ?? $u['timezone'];
$image = $u['profile_image'];
if (!empty($_FILES['profile_image'])) {
    $f = $_FILES['profile_image'];
    if ($f['error'] !== UPLOAD_ERR_OK) fail(upload_error_message((int)$f['error']), 400, 'UPLOAD_FAILED');
    if ($f['size'] > 5 * 1048576) fail('Profile image must be at most 5 MB', 413, 'FILE_TOO_LARGE');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !@getimagesize($f['tmp_name'])) fail('Profile image must be JPG, PNG or WEBP', 415, 'BAD_MEDIA');
    $rel = make_thumbnail($f['tmp_name'], $mime, 'images/avatars/' . random_token(12) . '.jpg');
    if (!$rel) fail('Could not process the image', 400, 'BAD_MEDIA');
    $image = $rel;
}
db_run('UPDATE users SET full_name = ?, phone = ?, business_name = ?, timezone = ?, profile_image = ? WHERE user_id = ?',
    [$name, $phone, $biz, $tz, $image, $u['user_id']]);
log_activity((int)$u['user_id'], 'auth.profile_update', 'user', (int)$u['user_id'], 'Updated profile');
$fresh = db_one('SELECT u.*, r.role_key FROM users u JOIN roles r USING(role_id) WHERE user_id = ?', [$u['user_id']]);
ok(['user' => present_user($fresh)], 'Profile updated');
