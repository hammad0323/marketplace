<?php
/**
 * Secure image uploads: size limit, MIME sniffing with finfo, getimagesize
 * check, random file names, and GD re-encoding (which strips metadata and
 * anything smuggled after the image data). SVG is never accepted.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

const UPLOAD_MAX_BYTES = 8 * 1024 * 1024;
const UPLOAD_MAX_DIM = 2400;

function upload_error_text(int $code): string
{
    return [
        UPLOAD_ERR_INI_SIZE => 'The file is larger than the server allows.',
        UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted.',
        UPLOAD_ERR_NO_FILE => 'No file was selected.',
    ][$code] ?? 'The upload failed.';
}

/**
 * Save one uploaded image. $file is an entry from $_FILES (or a normalised
 * multi-file entry). Returns the stored relative path (uploads/dir/name.ext).
 * Throws RuntimeException with a user-friendly message on failure.
 */
function upload_image(array $file, string $dir): string
{
    $dir = in_list($dir, ['products', 'categories', 'banners', 'content', 'branding', 'collections'], 'content');
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_text((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Images must be 8 MB or smaller.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
    if (!isset($types[$mime])) {
        throw new RuntimeException('Only JPG, PNG and WebP images are allowed.');
    }
    $ext = $types[$mime];
    $target = ROOT_PATH . '/uploads/' . $dir . '/' . date('Y/m');
    if (!is_dir($target) && !mkdir($target, 0755, true)) {
        throw new RuntimeException('Upload folder is not writable.');
    }
    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = $target . '/' . $name;

    if ($ext === 'ico') {
        if ($dir !== 'branding' || $file['size'] > 200 * 1024) throw new RuntimeException('Icon files are only allowed for the favicon.');
        move_uploaded_file($file['tmp_name'], $dest);
    } else {
        $info = @getimagesize($file['tmp_name']);
        if (!$info || $info[0] < 10 || $info[1] < 10) {
            throw new RuntimeException('The file is not a valid image.');
        }
        if (!function_exists('imagecreatefromjpeg')) {
            move_uploaded_file($file['tmp_name'], $dest);
        } else {
            $src = match ($ext) {
                'jpg' => @imagecreatefromjpeg($file['tmp_name']),
                'png' => @imagecreatefrompng($file['tmp_name']),
                'webp' => @imagecreatefromwebp($file['tmp_name']),
            };
            if (!$src) throw new RuntimeException('The image could not be read.');
            [$w, $h] = [imagesx($src), imagesy($src)];
            $scale = min(1, UPLOAD_MAX_DIM / max($w, $h));
            $nw = (int)round($w * $scale);
            $nh = (int)round($h * $scale);
            $out = imagecreatetruecolor($nw, $nh);
            if ($ext !== 'jpg') {
                imagealphablending($out, false);
                imagesavealpha($out, true);
            }
            imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = match ($ext) {
                'jpg' => imagejpeg($out, $dest, 86),
                'png' => imagepng($out, $dest, 7),
                'webp' => imagewebp($out, $dest, 85),
            };
            imagedestroy($src);
            imagedestroy($out);
            if (!$ok) throw new RuntimeException('The image could not be saved.');
        }
    }
    @chmod($dest, 0644);
    return 'uploads/' . $dir . '/' . date('Y/m') . '/' . $name;
}

/** Optional single upload from a form field; returns null when no file was chosen. */
function upload_optional(string $field, string $dir): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return upload_image($_FILES[$field], $dir);
}

/** Normalise a multi-file input (name="images[]") into a list of file arrays. */
function upload_files_list(string $field): array
{
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) return [];
    $list = [];
    foreach ($_FILES[$field]['name'] as $i => $n) {
        if (($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $list[] = [
            'name' => $n,
            'type' => $_FILES[$field]['type'][$i],
            'tmp_name' => $_FILES[$field]['tmp_name'][$i],
            'error' => $_FILES[$field]['error'][$i],
            'size' => $_FILES[$field]['size'][$i],
        ];
    }
    return $list;
}

/** Delete a previously uploaded file (only inside /uploads). */
function upload_delete(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/') || str_contains($path, '..')) return;
    $full = ROOT_PATH . '/' . $path;
    if (is_file($full)) @unlink($full);
}
