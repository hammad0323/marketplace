<?php
/**
 * Makes TV-safe versions of uploaded videos (see includes/video_optimize.php).
 *
 *   php cron/optimize_videos.php --id=123     one file (started automatically after an upload)
 *   php cron/optimize_videos.php              every waiting file (cron safety net, e.g. every 5 minutes)
 *   php cron/optimize_videos.php --all        also convert videos uploaded before this feature existed
 */
require __DIR__ . '/_cli.php';
@set_time_limit(0);

if (!video_tools_available()) { out('ffmpeg/ffprobe not found — set FFMPEG_PATH / FFPROBE_PATH or install ffmpeg.'); exit(1); }
if (video_cfg('VIDEO_OPTIMIZE', '1') === '0') { out('VIDEO_OPTIMIZE is off.'); exit(0); }

$opts = getopt('', ['id:', 'all']);
if (isset($opts['all'])) {
    $n = db_exec("UPDATE media_library SET optimize_status = 'pending' WHERE file_type = 'video' AND deleted_at IS NULL AND tv_path IS NULL AND optimize_status IN ('none', 'failed')");
    out("Queued {$n} older video(s).");
}
// A conversion that has been "processing" for over 2 hours was interrupted (server restart): retry it.
db_run("UPDATE media_library SET optimize_status = 'pending' WHERE optimize_status = 'processing' AND optimized_at < UTC_TIMESTAMP() - INTERVAL 2 HOUR");

$ids = isset($opts['id'])
    ? [(int)$opts['id']]
    : array_map('intval', array_column(db_all("SELECT media_id FROM media_library WHERE optimize_status = 'pending' AND deleted_at IS NULL ORDER BY media_id LIMIT 50"), 'media_id'));
foreach ($ids as $id) {
    $t = microtime(true);
    $note = optimize_video($id);
    out(sprintf('#%d %s (%.1fs)', $id, $note, microtime(true) - $t));
}
