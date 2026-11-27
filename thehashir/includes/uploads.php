<?php
/**
 * Secure image uploads into the existing images/ folders (so the current site and apps see them too).
 *
 *   images/venue-images      venue logos       (th_venue.profile_image = file name)
 *   images/venue-cover       venue covers      (th_venue.back_image = file name)
 *   images/details_picture   program/service/event images (th_details.profile_image_1 = file name)
 *   images/announcement      announcement images (th_announcement.news_image = file name)
 *   images/blogs             blog images (new folder; stored as "images/blogs/<file>")
 */

function media_dir()
{
    $base = MEDIA_DIR !== '' ? MEDIA_DIR : (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
    return rtrim(str_replace('\\', '/', $base), '/');
}

/**
 * Validate and store an uploaded image.
 * Returns array('ok' => true, 'file' => name) or array('ok' => false, 'error' => msg). Missing file -> ok + file ''.
 */
function upload_image($field, $folder, $maxMb = MAX_UPLOAD_MB)
{
    $allowedFolders = array('venue-images', 'venue-cover', 'details_picture', 'announcement', 'blogs');
    if (!in_array($folder, $allowedFolders, true)) {
        return array('ok' => false, 'error' => 'Upload folder not allowed.');
    }
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return array('ok' => true, 'file' => '');
    }
    $f = $_FILES[$field];
    if (is_array($f['error'])) {
        return array('ok' => false, 'error' => 'Upload one file at a time.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $msg = in_array($f['error'], array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE), true) ? 'The image is too large.' : 'The upload failed. Please try again.';
        return array('ok' => false, 'error' => $msg);
    }
    if ($f['size'] > $maxMb * 1024 * 1024) {
        return array('ok' => false, 'error' => 'The image must be ' . $maxMb . ' MB or smaller.');
    }
    if (!is_uploaded_file($f['tmp_name'])) {
        return array('ok' => false, 'error' => 'Invalid upload.');
    }
    $types = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif');
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string)finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
    }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !isset($types[$info['mime']]) || ($mime !== '' && !isset($types[$mime]))) {
        return array('ok' => false, 'error' => 'Only JPG, PNG, WebP or GIF images are allowed.');
    }
    if ($info[0] > 8000 || $info[1] > 8000) {
        return array('ok' => false, 'error' => 'The image dimensions are too large (max 8000 px).');
    }
    $ext = $types[$info['mime']];
    $base = blog_slugify(pathinfo($f['name'], PATHINFO_FILENAME));
    $base = substr($base !== '' ? $base : 'image', 0, 60);
    $name = time() . '_' . $base . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
    $dir = media_dir() . '/images/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return array('ok' => false, 'error' => 'The images folder is not writable on the server.');
    }
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        return array('ok' => false, 'error' => 'The image could not be saved on the server.');
    }
    @chmod($dir . '/' . $name, 0644);
    return array('ok' => true, 'file' => $name);
}
