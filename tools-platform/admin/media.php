<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    tp_require_csrf();
    $validation = tp_validate_image_upload($_FILES['file']);
    if (!$validation['ok']) {
        $error = $validation['error'];
    } else {
        $fileName = tp_safe_upload_name($_FILES['file']['name'], $validation['extension']);
        $destDir = TOOLS_PLATFORM_ROOT . '/uploads/categories';
        $dest = $destDir . '/' . $fileName;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $publicPath = tp_url('uploads/categories/' . $fileName);
            $altText = tp_sanitize_text($_POST['alt_text'] ?? '', 200);
            tp_execute(
                'INSERT INTO media (file_name, file_path, file_type, file_size, alt_text, uploaded_by) VALUES (?,?,?,?,?,?)',
                'sssisi',
                [$fileName, $publicPath, $validation['mime'], (int) $_FILES['file']['size'], $altText, $_SESSION['admin_id']]
            );
            tp_flash_set('success', 'Image uploaded.');
        } else {
            $error = 'Could not save the uploaded file — check the uploads/ folder is writable.';
        }
    }
}

$mediaItems = tp_query('SELECT * FROM media ORDER BY created_at DESC LIMIT 200');
$adminPageTitle = 'Media Library';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="admin-card mb-3">
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
    <?= tp_csrf_field() ?>
    <div class="flex-grow-1">
      <label class="form-label">Upload Image</label>
      <input type="file" name="file" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif" required>
    </div>
    <button class="btn tp-btn-calc" style="width:auto;">Upload</button>
  </form>
  <p class="text-muted small mt-2 mb-0">Uploaded files are validated by real image content (not just file extension) and capped at 5MB. Use the resulting URL in any Featured Image / OG Image field.</p>
</div>
<div class="row g-3">
  <?php foreach ($mediaItems as $m): ?>
    <div class="col-md-3">
      <div class="admin-card p-2 text-center">
        <img src="<?= e($m['file_path']) ?>" class="img-fluid rounded mb-2" style="max-height:120px;object-fit:cover;" alt="<?= e($m['alt_text'] ?? '') ?>">
        <input type="text" class="form-control form-control-sm" value="<?= e($m['file_path']) ?>" readonly onclick="this.select()">
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (!$mediaItems): ?><p class="text-muted">No media uploaded yet.</p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
