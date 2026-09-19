<?php
/**
 * One-time backfill: re-encodes already-uploaded avatars/logo/featured
 * images to WebP and shrinks them to the same max dimensions new uploads
 * now get via handle_upload()'s $imageMaxSize (see includes/functions.php).
 * Existing files predate that fix, so they're still full-size originals —
 * this is what actually resolves an image-size audit finding for anything
 * uploaded before this update; new uploads are already covered going
 * forward and don't need this script run again for them.
 *
 * Run once after deploying, from the project root:
 *   php scripts/backfill-optimize-images.php
 *
 * Safe to re-run — anything already .webp and within its size cap is
 * skipped, so a partial run can simply be run again.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("This script is CLI-only.\n");
}

require __DIR__ . '/../config/config.php';

/**
 * @param string $label Human-readable name for the progress line.
 * @param string $relativePath Current value from the DB (relative to UPLOAD_PATH).
 * @return string|null The new relative path if the file was re-encoded, else null.
 */
function backfill_one($label, $relativePath, $maxWidth, $maxHeight)
{
    if (!$relativePath) {
        return null;
    }
    $absPath = UPLOAD_PATH . '/' . $relativePath;
    if (!is_file($absPath)) {
        echo "  skip (file missing): $label -> $relativePath\n";
        return null;
    }
    $beforeSize = filesize($absPath);
    $newPath = optimize_uploaded_image($relativePath, $maxWidth, $maxHeight);
    if ($newPath === $relativePath) {
        echo "  skip (already optimized or not a raster image): $label\n";
        return null;
    }
    $afterSize = filesize(UPLOAD_PATH . '/' . $newPath);
    printf("  %s: %s -> %s (%.0f KB -> %.0f KB)\n", $label, $relativePath, $newPath, $beforeSize / 1024, $afterSize / 1024);
    return $newPath;
}

$db = db();

echo "Avatars (users.avatar)...\n";
$res = mysqli_query($db, "SELECT id, avatar FROM users WHERE avatar IS NOT NULL AND avatar != ''");
$update = mysqli_prepare($db, 'UPDATE users SET avatar = ? WHERE id = ?');
while ($row = mysqli_fetch_assoc($res)) {
    $newPath = backfill_one('user #' . $row['id'], $row['avatar'], 320, 320);
    if ($newPath !== null) {
        mysqli_stmt_bind_param($update, 'si', $newPath, $row['id']);
        mysqli_stmt_execute($update);
    }
}

echo "Site logo (site_settings.site_logo)...\n";
$logo = get_setting('site_logo', '');
if ($logo) {
    $newPath = backfill_one('site_logo', $logo, 480, 480);
    if ($newPath !== null) {
        $stmt = mysqli_prepare($db, "INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_logo', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        mysqli_stmt_bind_param($stmt, 's', $newPath);
        mysqli_stmt_execute($stmt);
    }
}

echo "Medicine featured images...\n";
$res = mysqli_query($db, "SELECT id, featured_image FROM medicine_info WHERE featured_image IS NOT NULL AND featured_image != ''");
$update = mysqli_prepare($db, 'UPDATE medicine_info SET featured_image = ? WHERE id = ?');
while ($row = mysqli_fetch_assoc($res)) {
    $newPath = backfill_one('medicine #' . $row['id'], $row['featured_image'], 1200, 1200);
    if ($newPath !== null) {
        mysqli_stmt_bind_param($update, 'si', $newPath, $row['id']);
        mysqli_stmt_execute($update);
    }
}

echo "Blog featured images...\n";
$res = mysqli_query($db, "SELECT id, featured_image FROM blog_posts WHERE featured_image IS NOT NULL AND featured_image != ''");
$update = mysqli_prepare($db, 'UPDATE blog_posts SET featured_image = ? WHERE id = ?');
while ($row = mysqli_fetch_assoc($res)) {
    $newPath = backfill_one('blog post #' . $row['id'], $row['featured_image'], 1200, 1200);
    if ($newPath !== null) {
        mysqli_stmt_bind_param($update, 'si', $newPath, $row['id']);
        mysqli_stmt_execute($update);
    }
}

echo "Product images...\n";
$res = mysqli_query($db, "SELECT id, image FROM doctor_products WHERE image IS NOT NULL AND image != ''");
$update = mysqli_prepare($db, 'UPDATE doctor_products SET image = ? WHERE id = ?');
while ($row = mysqli_fetch_assoc($res)) {
    $newPath = backfill_one('product #' . $row['id'], $row['image'], 1200, 1200);
    if ($newPath !== null) {
        mysqli_stmt_bind_param($update, 'si', $newPath, $row['id']);
        mysqli_stmt_execute($update);
    }
}

echo "Done.\n";
