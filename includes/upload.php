<?php
/**
 * Secure image uploads: validates MIME by content, size and dimensions,
 * re-encodes through GD (strips any embedded payload), resizes and stores
 * a WebP version plus a thumbnail in /uploads/{folder}/YYYY/MM/.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

/**
 * Normalise $_FILES['x'] (single or multiple) into a flat list.
 */
function upload_files_list(string $field): array
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return ($f['error'] === UPLOAD_ERR_NO_FILE) ? [] : [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

/**
 * Process one uploaded image.
 * @return array ['ok'=>bool, 'path'=>'products/2026/01/abc.webp', 'thumb'=>..., 'error'=>...]
 */
function upload_image(array $file, string $folder, int $maxW = 1200, int $thumbW = 400, bool $localFile = false): array
{
    $allowedFolders = ['products', 'categories', 'banners', 'brands', 'promotions', 'misc'];
    if (!in_array($folder, $allowedFolders, true)) {
        return ['ok' => false, 'error' => 'Invalid upload folder.'];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed (code ' . (int) $file['error'] . ').'];
    }
    if ($file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Image is larger than ' . MAX_UPLOAD_MB . ' MB.'];
    }
    if (!$localFile && !is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') {
        return ['ok' => false, 'error' => 'Invalid upload.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WebP or GIF images are allowed.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] < 10 || $info[1] < 10 || $info[0] > 8000 || $info[1] > 8000) {
        return ['ok' => false, 'error' => 'The file is not a valid image.'];
    }
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($file['tmp_name']); break;
        case 'image/png':  $src = @imagecreatefrompng($file['tmp_name']); break;
        case 'image/webp': $src = @imagecreatefromwebp($file['tmp_name']); break;
        default:           $src = @imagecreatefromgif($file['tmp_name']);
    }
    if (!$src) {
        return ['ok' => false, 'error' => 'Could not read the image.'];
    }
    $sub = $folder . '/' . date('Y/m');
    $dir = FS_ROOT . '/uploads/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return ['ok' => false, 'error' => 'Upload folder is not writable.'];
    }
    $name = bin2hex(random_bytes(8));
    $useWebp = function_exists('imagewebp');
    $ext = $useWebp ? 'webp' : 'jpg';

    $main = $sub . '/' . $name . '.' . $ext;
    $thumb = $sub . '/' . $name . '-t.' . $ext;
    $ok = image_save_resized($src, FS_ROOT . '/uploads/' . $main, $maxW, $useWebp)
        && image_save_resized($src, FS_ROOT . '/uploads/' . $thumb, $thumbW, $useWebp);
    imagedestroy($src);
    if (!$ok) {
        return ['ok' => false, 'error' => 'Could not save the image.'];
    }
    return ['ok' => true, 'path' => $main, 'thumb' => $thumb];
}

function image_save_resized($src, string $dest, int $maxW, bool $webp): bool
{
    $w = imagesx($src);
    $h = imagesy($src);
    $nw = min($w, $maxW);
    $nh = (int) round($h * ($nw / $w));
    $dst = imagecreatetruecolor($nw, $nh);
    // White background (for transparent PNG -> JPG) but keep alpha for WebP
    if ($webp) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = $webp ? imagewebp($dst, $dest, 82) : imagejpeg($dst, $dest, 85);
    imagedestroy($dst);
    return $ok;
}

/** Delete an uploaded file and its thumbnail (only inside /uploads). */
function upload_delete(?string $path): void
{
    if (!$path || strpos($path, '..') !== false || strpos($path, 'assets/') === 0 || strpos($path, '/sample/') !== false) {
        return;
    }
    $full = FS_ROOT . '/uploads/' . ltrim($path, '/');
    if (is_file($full)) {
        @unlink($full);
    }
}

/** Convenience: handle a single-file form field, returns new path or null. */
function upload_field(string $field, string $folder, int $maxW = 1600, ?string &$error = null): ?string
{
    $files = upload_files_list($field);
    if (!$files) {
        return null;
    }
    $r = upload_image($files[0], $folder, $maxW, 500);
    if (!$r['ok']) {
        $error = $r['error'];
        return null;
    }
    return $r['path'];
}
