<?php
// Soft delete: hides the user, revokes sessions, disconnects their displays. Email is freed for reuse.
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$admin = require_admin();
$u = admin_user_row(v_int(input(), 'user_id', true, 1));
if ((int)$u['user_id'] === (int)$admin['user_id']) fail('You cannot delete your own account', 400, 'VALIDATION');
db_begin();
db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$u['user_id']]);
db_run('DELETE dp FROM device_playlists dp JOIN devices d USING (device_id) WHERE d.user_id = ?', [$u['user_id']]);
db_run("UPDATE devices SET user_id = NULL, status = 'unpaired', current_playlist_id = NULL, current_screen_id = NULL, location_id = NULL WHERE user_id = ?", [$u['user_id']]);
db_run("UPDATE users SET deleted_at = UTC_TIMESTAMP(), status = 'inactive', email = CONCAT('deleted+', user_id, '+', email) WHERE user_id = ?", [$u['user_id']]);
db_run('UPDATE screens SET deleted_at = UTC_TIMESTAMP() WHERE user_id = ? AND deleted_at IS NULL', [$u['user_id']]);
db_run('UPDATE playlists SET deleted_at = UTC_TIMESTAMP() WHERE user_id = ? AND deleted_at IS NULL', [$u['user_id']]);
db_run('UPDATE media_library SET deleted_at = UTC_TIMESTAMP() WHERE user_id = ? AND deleted_at IS NULL', [$u['user_id']]);
log_activity((int)$admin['user_id'], 'admin.user.delete', 'user', (int)$u['user_id'], "Deleted {$u['email']}");
db_commit();
ok(null, 'User deleted');
