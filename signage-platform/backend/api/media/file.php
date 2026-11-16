<?php
/**
 * Streams a media file using a signed, expiring URL (no auth header needed,
 * so <img>/<video>/players can use it directly). Supports HTTP Range for video.
 *   ?id=<media_id>&v=file|thumb&exp=<unix>&sig=<hmac>
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET', 'HEAD');
$id = (int)($_GET['id'] ?? 0);
$variant = in_array($_GET['v'] ?? 'file', ['thumb', 'tv'], true) ? $_GET['v'] : 'file';
$exp = (int)($_GET['exp'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');
if ($id < 1 || $exp < time() || !hash_equals(media_signature($id, $exp, $variant), $sig)) {
    http_response_code(403); header('Content-Type: text/plain'); echo 'Link expired or invalid'; exit;
}
$m = db_one('SELECT file_path, thumbnail_path, tv_path, tv_checksum, mime_type, checksum_sha256, original_name FROM media_library WHERE media_id = ?', [$id]);
$rel = $m ? match ($variant) { 'thumb' => $m['thumbnail_path'] ?? $m['file_path'], 'tv' => $m['tv_path'] ?? $m['file_path'], default => $m['file_path'] } : null;
$abs = $rel ? realpath(storage_abs($rel)) : false;
$root = realpath(STORAGE_PATH);
if (!$abs || !$root || !str_starts_with($abs, $root) || !is_file($abs)) { http_response_code(404); header('Content-Type: text/plain'); echo 'Not found'; exit; }

$mime = $variant === 'thumb' && $m['thumbnail_path'] ? 'image/jpeg' : ($variant === 'tv' && $m['tv_path'] ? 'video/mp4' : $m['mime_type']);
$size = filesize($abs);
$sum = $variant === 'tv' && $m['tv_path'] ? $m['tv_checksum'] : $m['checksum_sha256'];
$etag = '"' . substr($sum ?? sha256($abs), 0, 32) . $variant . '"';
header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('ETag: ' . $etag);
header('Cache-Control: private, max-age=604800, immutable');
header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $m['original_name']) . '"');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }

$start = 0; $end = $size - 1;
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $r)) {
    if ($r[1] === '' && $r[2] !== '') { $start = max(0, $size - (int)$r[2]); }
    else { $start = (int)$r[1]; if ($r[2] !== '') $end = min($end, (int)$r[2]); }
    if ($start > $end || $start >= $size) { http_response_code(416); header("Content-Range: bytes */{$size}"); exit; }
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}
// Production: let the web server send the bytes (handles Range, sendfile(), no PHP worker held).
//   MEDIA_XSENDFILE=apache  → mod_xsendfile (XSendFilePath <STORAGE_PATH>)
//   MEDIA_XSENDFILE=nginx   → X-Accel-Redirect to an internal location mapped to STORAGE_PATH:
//                             location /_protected_media/ { internal; alias <STORAGE_PATH>/; }
$mode = defined('MEDIA_XSENDFILE') ? strtolower((string)MEDIA_XSENDFILE) : '';
if ($mode === 'apache' || $mode === 'nginx') {
    if (http_response_code() === 206) http_response_code(200);   // the web server answers the Range itself
    header_remove('Content-Range');
    if ($mode === 'apache') header('X-Sendfile: ' . $abs);
    else header('X-Accel-Redirect: /_protected_media/' . str_replace('%2F', '/', rawurlencode(ltrim(substr($abs, strlen($root)), '/'))));
    exit;
}

header('Content-Length: ' . ($end - $start + 1));
if (request_method() === 'HEAD') exit;

// Long videos must never be cut off half way (that is what makes players stall and re-buffer).
@set_time_limit(0);
@ini_set('zlib.output_compression', '0');            // compression breaks byte ranges / Content-Length
while (ob_get_level() > 0) @ob_end_clean();          // stream straight to the client
db()->close();                                        // do not hold a DB connection while streaming
$fp = fopen($abs, 'rb');
fseek($fp, $start);
$left = $end - $start + 1;
while ($left > 0 && !feof($fp)) {
    $chunk = fread($fp, (int)min(262144, $left));
    echo $chunk;
    $left -= strlen($chunk);
    flush();
}
fclose($fp);
exit;
