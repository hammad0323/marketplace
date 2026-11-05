<?php
/**
 * Secure upload pipeline:
 *  extension allow-list → finfo MIME sniffing → per-type / per-plan size caps →
 *  storage quota → random file name in dated folder → image re-encode check →
 *  thumbnail (GD) → metadata row in media_library.
 */

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        default => 'The upload failed on the server',
    };
}

/** Classify a file. Returns [file_type, mime, ext] or fails. */
function classify_upload(string $tmpPath, string $originalName, array $user, array $limits): array
{
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpPath);

    $imageMimes = app_setting('allowed_image_mimes', IMAGE_MIMES);
    $videoMimes = app_setting('allowed_video_mimes', VIDEO_MIMES);
    $docMimes   = app_setting('allowed_document_mimes', DOCUMENT_MIMES);

    if (in_array($ext, IMAGE_EXT, true) && in_array($mime, $imageMimes, true)) {
        if (@getimagesize($tmpPath) === false) fail('The image file is corrupted or not a real image', 415, 'BAD_MEDIA');
        return ['image', $mime, $ext === 'jpeg' ? 'jpg' : $ext];
    }
    if (in_array($ext, VIDEO_EXT, true) && in_array($mime, $videoMimes, true)) {
        if (!$limits['can_upload_video'] || !has_permission($user, 'media.upload_video')) {
            fail('Video uploads are not included in your plan', 403, 'VIDEO_NOT_ALLOWED');
        }
        return ['video', $mime, 'mp4'];
    }
    if (in_array($ext, DOCUMENT_EXT, true) && in_array($mime, $docMimes, true)) {
        return ['document', $mime, 'pdf'];
    }
    fail("Unsupported file type ({$mime}). Allowed: JPG, PNG, WEBP, GIF, MP4 (H.264) and PDF.", 415, 'BAD_MEDIA');
}

function storage_abs(string $relative): string
{
    return rtrim(STORAGE_PATH, '/') . '/' . ltrim($relative, '/');
}

/** Create a JPEG thumbnail. Returns relative path or null. */
function make_thumbnail(string $absSource, string $mime, string $relTarget): ?string
{
    if (!function_exists('imagecreatetruecolor')) return null;
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($absSource),
        'image/png'  => @imagecreatefrompng($absSource),
        'image/gif'  => @imagecreatefromgif($absSource),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absSource) : false,
        default => false,
    };
    if (!$src) return null;
    $w = imagesx($src); $h = imagesy($src);
    $tw = min(THUMB_WIDTH, $w); $th = max(1, (int)round($h * $tw / max(1, $w)));
    $dst = imagecreatetruecolor($tw, $th);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
    $abs = storage_abs($relTarget);
    @mkdir(dirname($abs), 0775, true);
    $ok = imagejpeg($dst, $abs, 78);
    return $ok ? $relTarget : null;
}

/**
 * Handle $_FILES[$field]. Returns the inserted media row (presented).
 */
function handle_media_upload(array $user, string $field = 'file', ?int $folderId = null, ?string $forceType = null): array
{
    if (empty($_FILES[$field])) fail('No file was uploaded', 400, 'VALIDATION', [$field => 'File is required']);
    $f = $_FILES[$field];
    if (is_array($f['error'])) fail('Upload one file per request', 400, 'VALIDATION');
    if ($f['error'] !== UPLOAD_ERR_OK) fail(upload_error_message((int)$f['error']), 400, 'UPLOAD_FAILED');
    if (!is_uploaded_file($f['tmp_name']) && APP_ENV === 'production') fail('Invalid upload', 400, 'UPLOAD_FAILED');

    $uid = (int)$user['user_id'];
    $limits = effective_limits($uid);
    $original = mb_substr(basename((string)$f['name']), 0, 255);
    [$type, $mime, $ext] = classify_upload($f['tmp_name'], $original, $user, $limits);
    $size = (int)filesize($f['tmp_name']);

    $globalCap = match ($type) {
        'video' => (int)app_setting('max_video_upload_mb', 500),
        default => (int)app_setting('max_image_upload_mb', 20),
    };
    if ($size > $globalCap * 1048576) fail("Files of this type may be at most {$globalCap} MB", 413, 'FILE_TOO_LARGE');
    assert_storage($uid, $size);

    $dirType = ['image' => 'images', 'video' => 'videos', 'document' => 'documents'][$type];
    $name = random_token(16) . '.' . $ext;
    $rel = $dirType . '/' . gmdate('Y/m') . '/' . $name;
    $abs = storage_abs($rel);
    if (!is_dir(dirname($abs)) && !@mkdir(dirname($abs), 0775, true)) fail('Storage is not writable', 500, 'STORAGE');
    $moved = is_uploaded_file($f['tmp_name']) ? move_uploaded_file($f['tmp_name'], $abs) : rename($f['tmp_name'], $abs);
    if (!$moved) fail('Could not store the file', 500, 'STORAGE');
    @chmod($abs, 0644);

    $width = $height = null; $thumb = null;
    if ($type === 'image') {
        [$width, $height] = getimagesize($abs) ?: [null, null];
        $thumb = make_thumbnail($abs, $mime, 'thumbnails/' . gmdate('Y/m') . '/' . pathinfo($name, PATHINFO_FILENAME) . '.jpg');
    }
    if ($forceType === 'logo' && $type === 'image') $type = 'logo';

    if ($folderId !== null) owned_row('media_folders', 'folder_id', $folderId, $user);

    $mediaId = db_insert('INSERT INTO media_library (user_id, folder_id, file_name, original_name, file_path, thumbnail_path, file_type, mime_type, file_size, width, height, duration_ms, checksum_sha256)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$uid, $folderId, $name, $original, $rel, $thumb, $type, $mime, $size, $width, $height,
            isset($_POST['duration_ms']) && is_numeric($_POST['duration_ms']) ? (int)$_POST['duration_ms'] : null, hash_file('sha256', $abs)]);
    log_activity($uid, 'media.upload', 'media', $mediaId, "Uploaded {$original}");
    return present_media(db_one('SELECT * FROM media_library WHERE media_id = ?', [$mediaId]));
}

/** Physically remove a media file + thumbnail. */
function delete_media_files(array $m): void
{
    foreach (['file_path', 'thumbnail_path'] as $k) {
        if (!empty($m[$k])) {
            $abs = storage_abs($m[$k]);
            $real = realpath($abs);
            $root = realpath(STORAGE_PATH);
            if ($real && $root && str_starts_with($real, $root)) @unlink($real);
        }
    }
}
