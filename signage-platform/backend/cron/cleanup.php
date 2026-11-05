<?php
// Daily: purge old soft-deleted data + files, expired tokens, rate-limit rows, old logs.
require __DIR__ . '/_cli.php';
$days = 30;
$n = 0;
foreach (db_all('SELECT * FROM media_library WHERE deleted_at < UTC_TIMESTAMP() - INTERVAL ? DAY', [$days]) as $m) {
    if (db_val('SELECT 1 FROM widget_assets WHERE media_id = ?', [$m['media_id']])) continue;   // still referenced by a draft
    if (db_val('SELECT 1 FROM screens WHERE background_media_id = ?', [$m['media_id']])) continue;
    // Keep files referenced by any current publication (players may still download them)
    if (db_val("SELECT 1 FROM screen_publications WHERE status = 'current' AND JSON_SEARCH(published_json, 'one', ?) IS NOT NULL", [$m['file_path']])) continue;
    delete_media_files($m);
    db_run('DELETE FROM media_library WHERE media_id = ?', [$m['media_id']]);
    $n++;
}
db_run('DELETE FROM screens WHERE deleted_at < UTC_TIMESTAMP() - INTERVAL ? DAY', [$days]);
db_run('DELETE FROM playlists WHERE deleted_at < UTC_TIMESTAMP() - INTERVAL ? DAY', [$days]);
db_run('DELETE FROM api_tokens WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 7 DAY OR revoked_at < UTC_TIMESTAMP() - INTERVAL 7 DAY');
db_run('DELETE FROM password_resets WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
db_run('DELETE FROM device_pairing_codes WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
db_run('DELETE FROM rate_limits WHERE window_start < UTC_TIMESTAMP() - INTERVAL 1 DAY');
db_run('DELETE FROM device_logs WHERE created_at < UTC_TIMESTAMP() - INTERVAL 90 DAY');
db_run('DELETE FROM playback_logs WHERE created_at < UTC_TIMESTAMP() - INTERVAL 365 DAY');
db_run("UPDATE device_commands SET status = 'expired' WHERE status IN ('pending','delivered') AND expires_at < UTC_TIMESTAMP()");
out("Purged {$n} media file(s) and expired records");
