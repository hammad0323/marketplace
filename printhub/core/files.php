<?php
/**
 * Private file storage for customer documents.
 * - Files live in STORAGE_PATH (configure it outside public_html); nothing there is ever served directly.
 * - Real type is detected from the content (finfo) and must match the extension.
 * - Names are random; paths are validated against traversal on every read.
 * - Downloads use short-lived HMAC-signed links tied to the file and an expiry time.
 * Public media (shop logos, category icons, CV template previews) is stored separately under storage/public.
 */

const PH_DOC_TYPES = [
    'application/pdf' => ['pdf', 'pdf'],
    'image/jpeg' => ['jpg', 'image'], 'image/png' => ['png', 'image'], 'image/webp' => ['webp', 'image'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx', 'docx'],
];

function storage_root(): string
{
    $root = rtrim(STORAGE_PATH, '/');
    if (!is_dir($root)) {
        @mkdir($root, 0750, true);
    }
    return realpath($root) ?: $root;
}

/** Absolute path for a stored relative path; refuses anything that escapes the storage folder. */
function storage_file(string $rel): ?string
{
    if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0") || !preg_match('#^[a-z0-9_/\-\.]+$#i', $rel)) {
        return null;
    }
    $full = storage_root() . '/' . ltrim($rel, '/');
    $real = realpath($full);
    return $real && str_starts_with($real, storage_root() . '/') ? $real : null;
}

function storage_new_path(string $area, string $ext): array
{
    $rel = $area . '/' . date('Y/m') . '/' . random_token(16) . '.' . $ext;
    $full = storage_root() . '/' . $rel;
    if (!is_dir(dirname($full)) && !@mkdir(dirname($full), 0750, true)) {
        throw new RuntimeException('Storage is not writable. Please contact the platform admin.');
    }
    return [$rel, $full];
}

function storage_delete(?string $rel): void
{
    if ($rel && ($f = storage_file($rel)) && is_file($f)) {
        @unlink($f);
    }
}

/**
 * Validates an uploaded document ($_FILES entry). Returns [mime, ext, kind] or throws with a customer-friendly message.
 */
function upload_validate(array $file, int $maxMb): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        throw new RuntimeException(in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'This file is too large.' : ($code === UPLOAD_ERR_NO_FILE ? 'Please choose a file.' : 'The upload failed. Please try again.'));
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > $maxMb * 1048576) {
        throw new RuntimeException('Files can be up to ' . $maxMb . ' MB.');
    }
    if ($file['size'] < 10) {
        throw new RuntimeException('This file is empty.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    if ($mime === 'application/zip' && $ext === 'docx') {
        $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
    if (!isset(PH_DOC_TYPES[$mime])) {
        throw new RuntimeException('Please upload a PDF, JPG, PNG, WebP or Word (.docx) file.');
    }
    [$canonical, $kind] = PH_DOC_TYPES[$mime];
    $allowedExt = $kind === 'image' ? ($canonical === 'jpg' ? ['jpg', 'jpeg'] : [$canonical]) : [$canonical];
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('The file extension does not match its content. Please upload the original file.');
    }
    if ($kind === 'image') {
        $info = @getimagesize($file['tmp_name']);
        if (!$info || $info[0] < 10 || $info[1] < 10 || $info[0] * $info[1] > 80000000) {
            throw new RuntimeException('This image could not be read.');
        }
    }
    if ($kind === 'pdf') {
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 1024);
        if (!str_starts_with(ltrim($head), '%PDF-')) {
            throw new RuntimeException('This PDF could not be read.');
        }
    }
    if ($kind === 'docx') {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true || $zip->locateName('word/document.xml') === false) {
            throw new RuntimeException('This Word file could not be read. Save it as .docx or PDF and try again.');
        }
        $zip->close();
    }
    return [$mime, $canonical, $kind];
}

/** Moves an uploaded file into private storage; returns the relative path. */
function upload_store(array $file, string $area, string $ext): string
{
    [$rel, $full] = storage_new_path($area, $ext);
    if (!move_uploaded_file($file['tmp_name'], $full)) {
        throw new RuntimeException('Could not save the file. Please try again.');
    }
    @chmod($full, 0640);
    return $rel;
}

/** Public images (logos, icons, previews): re-encoded with GD so no hidden payload survives. */
function media_store_image(array $file, string $area, int $maxW = 1200): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Please choose an image.');
    }
    if ($file['size'] > 5 * 1048576) {
        throw new RuntimeException('Images can be up to 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png' => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        default => false,
    };
    if (!$src) {
        throw new RuntimeException('Please upload a JPG, PNG or WebP image.');
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $s = min(1, $maxW / max($w, $h));
    $nw = max(1, (int) round($w * $s));
    $nh = max(1, (int) round($h * $s));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $rel = 'public/' . $area . '/' . random_token(12) . '.png';
    $full = storage_root() . '/' . $rel;
    if (!is_dir(dirname($full))) {
        @mkdir(dirname($full), 0755, true);
    }
    imagepng($dst, $full, 6);
    imagedestroy($src);
    imagedestroy($dst);
    return $rel;
}

// ---------------------------------------------------------------- temporary signed links
function signed_file_url(string $kind, int $id, int $ttl = 900, string $disposition = 'inline'): string
{
    $exp = time() + $ttl;
    $sig = substr(sign("$kind|$id|$exp|$disposition"), 0, 40);
    return url("files/$kind/$id?e=$exp&d=$disposition&s=$sig");
}

function signed_file_check(string $kind, int $id, int $exp, string $disposition, string $sig): bool
{
    return $exp >= time() && in_array($disposition, ['inline', 'attachment'], true) && hash_equals(substr(sign("$kind|$id|$exp|$disposition"), 0, 40), $sig);
}

/** Streams a private file with safe headers. */
function serve_file(string $full, string $mime, string $name, string $disposition = 'inline'): never
{
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: ' . $disposition . '; filename="' . preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:");
    readfile($full);
    exit;
}
