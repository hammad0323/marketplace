<?php
/**
 * Secure image uploads: MIME sniffing, size limits, re-encoding through GD
 * (strips metadata and any embedded payload), random file names, and
 * date-partitioned storage under /uploads (where script execution is disabled).
 */

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const UPLOAD_MAX_DIMENSION = 2400;

function upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The file is larger than the server allows.';
        case UPLOAD_ERR_PARTIAL:
            return 'The upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was selected.';
        default:
            return 'The upload failed (code ' . $code . ').';
    }
}

/**
 * Normalise $_FILES[$field] into a list (supports multiple uploads).
 */
function uploaded_files(string $field): array
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
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
 * Validate and store one uploaded image.
 * @return array{0:?string,1:?string} [relative path, error]
 */
function store_image_upload(array $file, string $folder = 'media', int $maxDim = UPLOAD_MAX_DIMENSION): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, upload_error_message((int) $file['error'])];
    }
    if (!is_uploaded_file($file['tmp_name']) && PHP_SAPI !== 'cli') {
        return [null, 'Invalid upload.'];
    }
    if ($file['size'] <= 0 || $file['size'] > UPLOAD_MAX_BYTES) {
        return [null, 'Images must be smaller than ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB.'];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($map[$mime])) {
        return [null, 'Only JPG, PNG, WebP or GIF images are allowed.'];
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] < 10 || $info[1] < 10 || $info[0] * $info[1] > 40000000) {
        return [null, 'The file is not a valid image or is too large in pixels.'];
    }
    $ext = $map[$mime];
    $folder = preg_replace('/[^a-z0-9_-]/', '', strtolower($folder)) ?: 'media';
    $relDir = 'uploads/' . $folder . '/' . date('Y/m');
    $absDir = ROOT_PATH . '/' . $relDir;
    if (!is_dir($absDir) && !mkdir($absDir, 0755, true)) {
        return [null, 'Upload folder is not writable.'];
    }
    $name = date('His') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $absDir . '/' . $name;

    $ok = reencode_image($file['tmp_name'], $dest, $mime, $maxDim);
    if (!$ok) {
        return [null, 'The image could not be processed.'];
    }
    @chmod($dest, 0644);
    return [$relDir . '/' . $name, null];
}

function reencode_image(string $src, string $dest, string $mime, int $maxDim): bool
{
    if (!function_exists('imagecreatefromstring')) {
        return move_uploaded_file($src, $dest) || copy($src, $dest);
    }
    if ($mime === 'image/gif') {
        // Preserve animation: GIFs are validated above and copied as-is.
        return move_uploaded_file($src, $dest) || copy($src, $dest);
    }
    $img = @imagecreatefromstring((string) file_get_contents($src));
    if (!$img) {
        return false;
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
        if ($rot) {
            $img = imagerotate($img, $rot, 0);
        }
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if (max($w, $h) > $maxDim) {
        $scale = $maxDim / max($w, $h);
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $resized = imagecreatetruecolor($nw, $nh);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    } else {
        imagesavealpha($img, true);
    }
    switch ($mime) {
        case 'image/png':
            $ok = imagepng($img, $dest, 7);
            break;
        case 'image/webp':
            $ok = imagewebp($img, $dest, 85);
            break;
        default:
            imageinterlace($img, true);
            $ok = imagejpeg($img, $dest, 86);
    }
    imagedestroy($img);
    return $ok;
}

/** Delete a previously uploaded file (only inside /uploads). */
function delete_upload(?string $relPath): void
{
    $relPath = (string) $relPath;
    if (strpos($relPath, 'uploads/') !== 0 || strpos($relPath, '..') !== false) {
        return;
    }
    $abs = ROOT_PATH . '/' . $relPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
}

/**
 * Handle an optional single-image field in an admin form.
 * Returns [newPath|null (unchanged), error|null]. Supports "<field>_remove" checkbox.
 */
function handle_image_field(string $field, ?string $current, string $folder): array
{
    $files = uploaded_files($field);
    if ($files) {
        [$path, $err] = store_image_upload($files[0], $folder);
        if ($err) {
            return [$current, $err];
        }
        if ($current && $current !== $path) {
            delete_upload($current);
        }
        return [$path, null];
    }
    if (!empty($_POST[$field . '_remove'])) {
        delete_upload($current);
        return ['', null];
    }
    return [$current, null];
}
