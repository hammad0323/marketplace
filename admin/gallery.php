<?php
require __DIR__ . '/../config.php';
wh_require_page_access('gallery');
$businessId = wh_current_business_id();
$halls = wh_get_halls($businessId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    wh_csrf_verify();
    $name = wh_input_post('category_name');
    if ($name !== '') {
        wh_insert('gallery_categories', ['business_id' => $businessId, 'name' => $name, 'slug' => wh_slugify($name)]);
        wh_flash_set('success', 'Category added.');
    }
    wh_redirect(BASE_URL . '/admin/gallery.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    wh_csrf_verify();
    $path = wh_handle_image_upload('image', 'gallery', 'gallery');
    if (!$path) {
        wh_flash_set('error', 'Image upload failed (max 3MB, JPG/PNG/WEBP only).');
    } else {
        wh_insert('gallery', [
            'business_id' => $businessId,
            'hall_id' => (int) wh_input_post('hall_id') ?: null,
            'category_id' => (int) wh_input_post('category_id') ?: null,
            'image_path' => $path,
            'title' => wh_input_post('title'),
            'alt_text' => wh_input_post('alt_text'),
            'is_featured' => wh_input_post('is_featured') === '1' ? 1 : 0,
        ]);
        wh_flash_set('success', 'Image uploaded.');
    }
    wh_redirect(BASE_URL . '/admin/gallery.php');
}

if (isset($_GET['delete'])) {
    wh_csrf_verify();
    $id = (int) $_GET['delete'];
    $img = wh_fetch_one('SELECT image_path FROM gallery WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
    if ($img) {
        wh_execute('DELETE FROM gallery WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
        @unlink(__DIR__ . '/../' . $img['image_path']);
        wh_flash_set('success', 'Image deleted.');
    }
    wh_redirect(BASE_URL . '/admin/gallery.php');
}
if (isset($_GET['feature'])) {
    wh_csrf_verify();
    $id = (int) $_GET['feature'];
    wh_execute("UPDATE gallery SET is_featured = IF(is_featured=1,0,1) WHERE id=? AND business_id=?", 'ii', [$id, $businessId]);
    wh_redirect(BASE_URL . '/admin/gallery.php');
}

$categories = wh_fetch_all('SELECT * FROM gallery_categories WHERE business_id=? ORDER BY name', 'i', [$businessId]);
$images = wh_fetch_all(
    'SELECT g.*, gc.name AS category_name FROM gallery g LEFT JOIN gallery_categories gc ON gc.id=g.category_id
     WHERE g.business_id=? ORDER BY g.id DESC',
    'i',
    [$businessId]
);

$pageTitle = 'Gallery';
$activePage = 'gallery';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Gallery Categories</h3>
    <form method="post" style="display:flex;gap:8px;">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="action" value="add_category">
      <input type="text" name="category_name" placeholder="New category name" style="width:220px;">
      <button type="submit" class="btn btn-light btn-sm">Add</button>
    </form>
  </div>
  <div class="badge-row">
    <?php foreach ($categories as $c): ?><span class="chip"><?= e($c['name']) ?></span><?php endforeach; ?>
    <?php if (!$categories): ?><span class="hint">No categories yet — add one above.</span><?php endif; ?>
  </div>
</div>

<div class="admin-card">
  <div class="card-head"><h3>Upload Image</h3></div>
  <form method="post" enctype="multipart/form-data">
    <?= wh_csrf_field() ?>
    <input type="hidden" name="action" value="upload">
    <div class="form-grid cols-3">
      <div class="form-group"><label>Image *</label><input type="file" name="image" accept="image/jpeg,image/png,image/webp" required></div>
      <div class="form-group"><label>Category</label>
        <select name="category_id"><option value="">—</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
      </div>
      <div class="form-group"><label>Hall (optional)</label>
        <select name="hall_id"><option value="">—</option><?php foreach ($halls as $h): ?><option value="<?= (int) $h['id'] ?>"><?= e($h['name']) ?></option><?php endforeach; ?></select>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group"><label>Title</label><input type="text" name="title"></div>
      <div class="form-group"><label>Alt Text (SEO)</label><input type="text" name="alt_text"></div>
    </div>
    <label><input type="checkbox" name="is_featured" value="1" style="width:auto;"> Featured image</label>
    <br><br>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
</div>

<div class="admin-card">
  <h3 style="margin-bottom:16px;">All Images (<?= count($images) ?>)</h3>
  <div class="gallery-grid">
    <?php foreach ($images as $img): ?>
    <div style="position:relative;">
      <div class="gallery-item"><img src="<?= e(BASE_URL . '/' . $img['image_path']) ?>" alt="<?= e($img['alt_text']) ?>"></div>
      <div style="display:flex;gap:4px;margin-top:6px;">
        <a href="<?= e(BASE_URL) ?>/admin/gallery.php?feature=<?= (int) $img['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-light btn-sm" style="flex:1;" title="Toggle featured"><i class="fa-solid <?= $img['is_featured'] ? 'fa-star' : 'fa-star' ?>" style="color:<?= $img['is_featured'] ? 'var(--a-warning)' : 'var(--a-muted)' ?>;"></i></a>
        <a href="<?= e(BASE_URL) ?>/admin/gallery.php?delete=<?= (int) $img['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" style="flex:1;" data-confirm="Delete this image?"><i class="fa-solid fa-trash"></i></a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (!$images): ?><p>No images uploaded yet.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
