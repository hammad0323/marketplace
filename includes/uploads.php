<?php
/**
 * Secure image uploads: size limit, real MIME detection, image decoding, re-encoding
 * (strips metadata / embedded payloads), resizing, random file names.
 */

const UC_IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

/**
 * Handles $_FILES[$field]. Returns the stored relative path ("2026/10/abc.webp"),
 * null when no file was uploaded, or throws RuntimeException with a user-facing message.
 */
function upload_image(string $field, int $maxWidth = 2000, ?array $file = null): ?string
{
    $file = $file ?? ($_FILES[$field] ?? null);
    if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (code ' . (int) $file['error'] . '). The file may be too large.');
    }
    $maxBytes = (defined('UPLOAD_MAX_MB') ? UPLOAD_MAX_MB : 5) * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        throw new RuntimeException('Image is larger than ' . (int) ($maxBytes / 1048576) . ' MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(UC_IMAGE_MIMES[$mime])) {
        throw new RuntimeException('Only JPG, PNG, WebP and GIF images are allowed.');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 40000000) {
        throw new RuntimeException('The file is not a valid image or its dimensions are too large.');
    }
    return store_image_file($file['tmp_name'], $mime, $maxWidth);
}

/** Multiple files from an input named "field[]". */
function upload_images(string $field, int $maxWidth = 2000): array
{
    $paths = [];
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) {
        return $paths;
    }
    foreach ($f['name'] as $i => $name) {
        $single = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
        $p = upload_image($field, $maxWidth, $single);
        if ($p) {
            $paths[] = $p;
        }
    }
    return $paths;
}

function store_image_file(string $tmp, string $mime, int $maxWidth): string
{
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png' => @imagecreatefrompng($tmp),
        'image/webp' => @imagecreatefromwebp($tmp),
        'image/gif' => @imagecreatefromgif($tmp),
        default => false,
    };
    if (!$src) {
        throw new RuntimeException('The image could not be processed.');
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $o = (int) ($exif['Orientation'] ?? 1);
        if (in_array($o, [3, 6, 8], true)) {
            $src = imagerotate($src, [3 => 180, 6 => -90, 8 => 90][$o], 0);
        }
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > $maxWidth) {
        $nh = (int) round($h * $maxWidth / $w);
        $dst = imagecreatetruecolor($maxWidth, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    } else {
        imagepalettetotruecolor($src);
        imagealphablending($src, false);
        imagesavealpha($src, true);
    }
    $sub = date('Y/m');
    $dir = UC_ROOT . '/uploads/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Upload folder is not writable.');
    }
    $useWebp = function_exists('imagewebp');
    $name = bin2hex(random_bytes(12)) . ($useWebp ? '.webp' : '.jpg');
    $ok = $useWebp ? imagewebp($src, $dir . '/' . $name, 84) : imagejpeg($src, $dir . '/' . $name, 86);
    imagedestroy($src);
    if (!$ok) {
        throw new RuntimeException('Could not save the image.');
    }
    return $sub . '/' . $name;
}

/** Deletes an uploaded file (ignores remote URLs and bundled assets). */
function delete_upload(?string $path): void
{
    if (!$path || preg_match('#^(https?:|assets/)#', $path) || str_contains($path, '..')) {
        return;
    }
    $full = UC_ROOT . '/uploads/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}
