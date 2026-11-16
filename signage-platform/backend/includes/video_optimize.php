<?php
/**
 * TV-safe video versions.
 *
 * Smart-TV browsers and TV boxes have weak hardware decoders and little memory: 4K, 60 fps,
 * very high bitrates, HEVC/10-bit or AC-3 audio make them show a black or frozen picture.
 * Every uploaded video therefore gets a "TV version" made in the background with ffmpeg:
 *
 *   MP4 · H.264 High@4.1 · 8-bit 4:2:0 · max 1920×1080 (1080×1920 portrait) · max 30 fps ·
 *   ~6 Mbit/s peak · AAC stereo · index at the front (+faststart) · keyframe every 2 s
 *
 * Files that already meet this are only repackaged (seconds, no quality loss). The original
 * stays untouched; players automatically receive the TV version (url, size and checksum)
 * once it exists — also for screens that were published before it was ready.
 * (WebP is an image format; MP4/H.264 is the video format every TV browser can decode.)
 */

const TV_MAX_LONG = 1920;
const TV_MAX_SHORT = 1080;
const TV_MAX_FPS = 30.5;
const TV_MAX_BITRATE = 8000000;   // bit/s; above this the file is re-encoded

function video_cfg(string $key, string $default): string
{
    return defined($key) && constant($key) !== '' ? (string)constant($key) : $default;
}

function video_optimize_enabled(): bool
{
    return video_cfg('VIDEO_OPTIMIZE', '1') !== '0' && video_tools_available();
}

/** True when ffmpeg + ffprobe can be run from PHP. */
function video_tools_available(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) return $ok = false;
    $out = []; $code = 1;
    @exec(escapeshellarg(video_cfg('FFPROBE_PATH', 'ffprobe')) . ' -version 2>&1', $out, $code);
    if ($code !== 0) return $ok = false;
    @exec(escapeshellarg(video_cfg('FFMPEG_PATH', 'ffmpeg')) . ' -version 2>&1', $out, $code);
    return $ok = ($code === 0);
}

/** ffprobe summary: codec, profile, level, pix_fmt, width/height (as displayed), fps, bitrate, audio codec, duration. */
function probe_video(string $abs): ?array
{
    $out = []; $code = 1;
    exec(escapeshellarg(video_cfg('FFPROBE_PATH', 'ffprobe')) . ' -v error -print_format json -show_streams -show_format ' . escapeshellarg($abs) . ' 2>/dev/null', $out, $code);
    $j = json_decode(implode("\n", $out), true);
    if ($code !== 0 || !is_array($j)) return null;
    $v = null; $a = null;
    foreach ($j['streams'] ?? [] as $s) {
        if (($s['codec_type'] ?? '') === 'video' && !$v && empty($s['disposition']['attached_pic'])) $v = $s;
        if (($s['codec_type'] ?? '') === 'audio' && !$a) $a = $s;
    }
    if (!$v) return null;
    $rot = 0;
    if (isset($v['tags']['rotate'])) $rot = (int)$v['tags']['rotate'];
    foreach ($v['side_data_list'] ?? [] as $sd) if (isset($sd['rotation'])) $rot = (int)$sd['rotation'];
    $w = (int)($v['width'] ?? 0); $h = (int)($v['height'] ?? 0);
    if (abs($rot) % 180 === 90) [$w, $h] = [$h, $w];
    $fps = 0.0;
    foreach (['avg_frame_rate', 'r_frame_rate'] as $k) {
        if (!empty($v[$k]) && preg_match('#^(\d+)/(\d+)$#', $v[$k], $m) && (int)$m[2] > 0 && (int)$m[1] > 0) { $fps = (int)$m[1] / (int)$m[2]; break; }
    }
    return [
        'codec' => strtolower((string)($v['codec_name'] ?? '')), 'profile' => (string)($v['profile'] ?? ''), 'level' => (int)($v['level'] ?? 0),
        'pix_fmt' => (string)($v['pix_fmt'] ?? ''), 'width' => $w, 'height' => $h, 'fps' => $fps,
        'bitrate' => (int)($v['bit_rate'] ?? 0) ?: (int)($j['format']['bit_rate'] ?? 0),
        'audio' => $a ? strtolower((string)($a['codec_name'] ?? '')) : null,
        'duration_ms' => (int)round(((float)($j['format']['duration'] ?? $v['duration'] ?? 0)) * 1000),
        'format' => (string)($j['format']['format_name'] ?? ''),
    ];
}

/** Reasons why a video must be re-encoded for TVs (empty = already TV-safe). */
function video_transcode_reasons(array $p): array
{
    $r = [];
    if ($p['codec'] !== 'h264') $r[] = strtoupper($p['codec'] ?: 'unknown') . ' codec';
    if ($p['pix_fmt'] !== 'yuv420p') $r[] = $p['pix_fmt'] . ' colour format';
    if (!in_array($p['profile'], ['Baseline', 'Constrained Baseline', 'Main', 'High', ''], true)) $r[] = $p['profile'] . ' profile';
    if ($p['codec'] === 'h264' && $p['level'] > 42) $r[] = 'level ' . $p['level'] / 10;
    if (max($p['width'], $p['height']) > TV_MAX_LONG || min($p['width'], $p['height']) > TV_MAX_SHORT) $r[] = $p['width'] . 'x' . $p['height'];
    if ($p['fps'] > TV_MAX_FPS) $r[] = round($p['fps']) . ' fps';
    if ($p['bitrate'] > TV_MAX_BITRATE) $r[] = round($p['bitrate'] / 1000000, 1) . ' Mbit/s';
    return $r;
}

function run_ffmpeg(array $args, ?string &$err = null): bool
{
    $cmd = escapeshellarg(video_cfg('FFMPEG_PATH', 'ffmpeg')) . ' -nostdin -hide_banner -loglevel error -y ' . implode(' ', $args) . ' 2>&1';
    if (PHP_OS_FAMILY !== 'Windows' && is_executable('/usr/bin/nice')) $cmd = '/usr/bin/nice -n 10 ' . $cmd;
    $out = []; $code = 1;
    exec($cmd, $out, $code);
    $err = mb_substr(trim(implode(' ', array_slice($out, -3))), 0, 240);
    return $code === 0;
}

/**
 * Create the TV version + poster for one media row. Returns a short human note.
 * Safe to call repeatedly; only one process works on a file at a time.
 */
function optimize_video(int $mediaId): string
{
    $claimed = db_exec("UPDATE media_library SET optimize_status = 'processing', optimized_at = UTC_TIMESTAMP()
                         WHERE media_id = ? AND file_type = 'video' AND deleted_at IS NULL AND optimize_status IN ('pending', 'failed')", [$mediaId]);
    if (!$claimed) return 'skipped (not pending)';
    $m = db_one('SELECT * FROM media_library WHERE media_id = ?', [$mediaId]);
    $finish = function (string $status, string $note, array $set = []) use ($mediaId) {
        $cols = ['optimize_status = ?', 'optimize_note = ?', 'optimized_at = UTC_TIMESTAMP()'];
        $vals = [$status, mb_substr($note, 0, 255)];
        foreach ($set as $k => $v) { $cols[] = "{$k} = ?"; $vals[] = $v; }
        db_run('UPDATE media_library SET ' . implode(', ', $cols) . ' WHERE media_id = ?', array_merge($vals, [$mediaId]));
        return $note;
    };
    try {
        $src = storage_abs($m['file_path']);
        if (!is_file($src)) return $finish('failed', 'Original file is missing');
        $p = probe_video($src);
        if (!$p) return $finish('failed', 'Not a readable video');

        $base = 'videos/tv/' . gmdate('Y/m') . '/' . pathinfo($m['file_name'], PATHINFO_FILENAME);
        $relOut = $base . '_tv.mp4';
        $absOut = storage_abs($relOut);
        @mkdir(dirname($absOut), 0775, true);
        $tmp = $absOut . '.part.mp4';
        $reasons = video_transcode_reasons($p);
        $audioOk = $p['audio'] === null || in_array($p['audio'], ['aac', 'mp3'], true);
        $err = '';
        if ($reasons) {
            $landscape = $p['width'] >= $p['height'];
            [$bw, $bh] = $landscape ? [TV_MAX_LONG, TV_MAX_SHORT] : [TV_MAX_SHORT, TV_MAX_LONG];
            $vf = "scale=w='min({$bw},iw)':h='min({$bh},ih)':force_original_aspect_ratio=decrease:force_divisible_by=2";
            if ($p['fps'] > TV_MAX_FPS) $vf .= ',fps=30';
            $args = ['-i', escapeshellarg($src), '-map 0:v:0', '-map 0:a:0?', '-vf', escapeshellarg($vf),
                '-c:v libx264 -preset veryfast -profile:v high -level:v 4.1 -pix_fmt yuv420p -crf 23 -maxrate 6M -bufsize 12M',
                '-g 60 -keyint_min 30 -sc_threshold 0',
                '-c:a aac -b:a 128k -ac 2 -ar 48000', '-movflags +faststart -max_muxing_queue_size 2048', escapeshellarg($tmp)];
            $note = 'Converted for TVs (' . implode(', ', $reasons) . ')';
        } else {
            // Already TV-safe: only move the index to the front (and fix unsupported audio).
            $args = ['-i', escapeshellarg($src), '-map 0:v:0', '-map 0:a:0?', '-c:v copy', $audioOk ? '-c:a copy' : '-c:a aac -b:a 128k -ac 2',
                '-movflags +faststart', escapeshellarg($tmp)];
            $note = 'Already TV-ready (optimised for streaming)';
        }
        if (!run_ffmpeg($args, $err) || !is_file($tmp) || filesize($tmp) < 1024) {
            @unlink($tmp);
            return $finish('failed', 'Conversion failed: ' . ($err ?: 'ffmpeg error'));
        }
        rename($tmp, $absOut);
        @chmod($absOut, 0644);
        $q = probe_video($absOut) ?? $p;

        // Poster frame (shown instead of a black box while the video loads, and as the thumbnail).
        $relThumb = $m['thumbnail_path'];
        if (!$relThumb) {
            $relThumb = 'thumbnails/' . gmdate('Y/m') . '/' . pathinfo($m['file_name'], PATHINFO_FILENAME) . '.jpg';
            @mkdir(dirname(storage_abs($relThumb)), 0775, true);
            $at = $q['duration_ms'] > 1500 ? '1' : '0';
            if (!run_ffmpeg(['-ss', $at, '-i', escapeshellarg($absOut), '-frames:v 1', '-vf', escapeshellarg('scale=' . THUMB_WIDTH . ':-2'), '-q:v 4', escapeshellarg(storage_abs($relThumb))])) $relThumb = null;
        }
        return $finish('done', $note, [
            'tv_path' => $relOut, 'tv_file_size' => filesize($absOut), 'tv_checksum' => hash_file('sha256', $absOut),
            'width' => $q['width'] ?: $p['width'], 'height' => $q['height'] ?: $p['height'],
            'duration_ms' => $q['duration_ms'] ?: ($m['duration_ms'] ?? null), 'thumbnail_path' => $relThumb,
        ]);
    } catch (Throwable $e) {
        app_log('video optimize #' . $mediaId . ': ' . $e->getMessage(), 'error.log');
        return $finish('failed', 'Conversion failed');
    }
}

/** Start the converter for one file in the background (falls back to the cron job). */
function spawn_video_optimizer(int $mediaId): void
{
    if (!video_optimize_enabled() || PHP_OS_FAMILY === 'Windows') return;
    $php = video_cfg('PHP_CLI_PATH', 'php');
    @exec(escapeshellarg($php) . ' ' . escapeshellarg(APP_ROOT . '/cron/optimize_videos.php') . ' --id=' . $mediaId . ' > /dev/null 2>&1 &');
}

/** The file players should receive: the TV version when ready, else the original. */
function media_playback(array $m): array
{
    if (($m['file_type'] ?? '') === 'video' && !empty($m['tv_path'])) {
        return ['variant' => 'tv', 'path' => $m['tv_path'], 'size' => (int)$m['tv_file_size'], 'checksum' => $m['tv_checksum'], 'mime' => 'video/mp4'];
    }
    return ['variant' => 'file', 'path' => $m['file_path'] ?? null, 'size' => (int)($m['file_size'] ?? 0), 'checksum' => $m['checksum_sha256'] ?? null, 'mime' => $m['mime_type'] ?? null];
}
