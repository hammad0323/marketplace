<?php
// multipart/form-data: file=<binary>, folder_id?, as_logo?, duration_ms? (videos, measured client-side)
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'media.upload');
rate_limit('upload:user:' . $u['user_id'], 120, 3600);
$folder = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? v_int($_POST, 'folder_id', false, 1) : null;
$media = handle_media_upload($u, 'file', $folder, !empty($_POST['as_logo']) ? 'logo' : null);
// Admins can upload shared files (template images & backgrounds usable by every user).
if (!empty($_POST['global']) && is_admin($u)) {
    db_run('UPDATE media_library SET is_global = 1 WHERE media_id = ?', [$media['media_id']]);
    $media['is_global'] = 1;
}
ok($media, 'File uploaded', 201);
