<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$banner = $id ? db_fetch_one("SELECT * FROM banners WHERE id=?", 'i', [$id]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $heading = trim($_POST['heading']); $subHeading = trim($_POST['sub_heading']);
    $description = clean_html($_POST['description'] ?? ''); $buttonText = trim($_POST['button_text']);
    $buttonUrl = trim($_POST['button_url']); $bgColor = trim($_POST['bg_color']);
    $textAlign = $_POST['text_align']; $status = $_POST['status']; $sortOrder = (int)$_POST['sort_order'];

    $imageUpload = handle_image_upload('image', 'banners');
    if (isset($imageUpload['error'])) { flash('error', $imageUpload['error']); redirect(admin_url('banners/form.php' . ($id ? "?id=$id" : ''))); }
    $imagePath = $imageUpload['path'] ?? ($banner['image'] ?? null);

    if ($banner) {
        db_exec("UPDATE banners SET heading=?,sub_heading=?,description=?,button_text=?,button_url=?,image=?,bg_color=?,text_align=?,status=?,sort_order=? WHERE id=?",
            'sssssssssii', [$heading, $subHeading, $description, $buttonText, $buttonUrl, $imagePath, $bgColor, $textAlign, $status, $sortOrder, $id]);
        flash('success', 'Banner updated.');
    } else {
        db_insert("INSERT INTO banners (heading,sub_heading,description,button_text,button_url,image,bg_color,text_align,status,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)",
            'sssssssssi', [$heading, $subHeading, $description, $buttonText, $buttonUrl, $imagePath, $bgColor, $textAlign, $status, $sortOrder]);
        flash('success', 'Banner created.');
    }
    redirect(admin_url('banners/index.php'));
}

$dashRole = 'admin'; $pageTitle = $banner ? 'Edit Banner' : 'Add Banner'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1><?= $banner ? 'Edit' : 'Add' ?> Banner</h1></div>
<div class="dash-form-card">
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label>Heading</label><input type="text" name="heading" value="<?= clean($banner['heading'] ?? '') ?>" required>
    <label>Sub Heading</label><input type="text" name="sub_heading" value="<?= clean($banner['sub_heading'] ?? '') ?>">
    <label>Description</label><textarea name="description" rows="3"><?= clean($banner['description'] ?? '') ?></textarea>
    <div class="form-row">
      <div><label>Button Text</label><input type="text" name="button_text" value="<?= clean($banner['button_text'] ?? '') ?>"></div>
      <div><label>Button URL (relative)</label><input type="text" name="button_url" value="<?= clean($banner['button_url'] ?? '') ?>" placeholder="category/electronics"></div>
    </div>
    <div class="form-row">
      <div><label>Background Image</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp">
        <?php if (!empty($banner['image'])): ?><img class="table-thumb" src="<?= upload_url($banner['image']) ?>"><?php endif; ?></div>
      <div><label>Background Color</label><input type="color" name="bg_color" value="<?= clean($banner['bg_color'] ?? '#2f6fed') ?>"></div>
    </div>
    <div class="form-row">
      <div><label>Text Alignment</label>
        <select name="text_align">
          <?php foreach (['left','center','right'] as $ta): ?><option value="<?= $ta ?>" <?= ($banner['text_align'] ?? 'left') === $ta ? 'selected' : '' ?>><?= ucfirst($ta) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Sort Order</label><input type="number" name="sort_order" value="<?= clean($banner['sort_order'] ?? '0') ?>"></div>
      <div><label>Status</label>
        <select name="status"><option value="active" <?= ($banner['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($banner['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Save Banner</button>
    <a href="<?= admin_url('banners/index.php') ?>" class="btn btn-outline">Cancel</a>
  </form>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
