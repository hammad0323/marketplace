<?php
/**
 * Streams a media file using a signed, expiring URL (no auth header needed,
 * so <img>/<video>/players can use it directly). Supports HTTP Range for video.
 *   ?id=<media_id>&v=file|thumb&exp=<unix>&sig=<hmac>
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET', 'HEAD');
$id = (int)($_GET['id'] ?? 0);
$variant = ($_GET['v'] ?? 'file') === 'thumb' ? 'thumb' : 'file';
$exp = (int)($_GET['exp'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');
if ($id < 1 || $exp < time() || !hash_equals(media_signature($id, $exp, $variant), $sig)) {
    http_response_code(403); header('Content-Type: text/plain'); echo 'Link expired or invalid'; exit;
}
$m = db_one('SELECT file_path, thumbnail_path, mime_type, checksum_sha256, original_name FROM media_library WHERE media_id = ?', [$id]);
$rel = $m ? ($variant === 'thumb' ? ($m['thumbnail_path'] ?? $m['file_path']) : $m['file_path']) : null;
$abs = $rel ? realpath(storage_abs($rel)) : false;
$root = realpath(STORAGE_PATH);
if (!$abs || !$root || !str_starts_with($abs, $root) || !is_file($abs)) { http_response_code(404); header('Content-Type: text/plain'); echo 'Not found'; exit; }

$mime = $variant === 'thumb' && $m['thumbnail_path'] ? 'image/jpeg' : $m['mime_type'];
$size = filesize($abs);
$etag = '"' . substr($m['checksum_sha256'] ?? sha256($abs), 0, 32) . $variant . '"';
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
header('Content-Length: ' . ($end - $start + 1));
if (request_method() === 'HEAD') exit;
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
