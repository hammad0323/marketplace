<?php
require __DIR__ . '/../config.php';
wh_require_page_access('banners');
$businessId = wh_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $id = (int) wh_input_post('id');
    $title = wh_input_post('title');
    if ($title === '') {
        wh_flash_set('error', 'Please enter a banner title.');
        wh_redirect(BASE_URL . '/admin/banners.php');
    }

    $data = [
        'title' => $title,
        'subheading' => wh_input_post('subheading'),
        'cta_text' => wh_input_post('cta_text'),
        'cta_link' => wh_input_post('cta_link'),
        'sort_order' => (int) wh_input_post('sort_order', 0),
        'status' => wh_input_post('status') === 'inactive' ? 'inactive' : 'active',
    ];

    $uploaded = wh_handle_image_upload('background_image', 'banners', 'banner');
    if ($uploaded === false) {
        wh_flash_set('error', 'Image upload failed (max 3MB, JPG/PNG/WEBP only).');
        wh_redirect(BASE_URL . '/admin/banners.php');
    }
    if ($uploaded) {
        $data['background_image'] = $uploaded;
    }

    if ($id) {
        wh_update('banners', $data, 'id = ? AND business_id = ?', [$id, $businessId]);
        wh_flash_set('success', 'Banner updated.');
    } else {
        $data['background_image'] = $data['background_image'] ?? '';
        $data['business_id'] = $businessId;
        wh_insert('banners', $data);
        wh_flash_set('success', 'Banner created.');
    }
    wh_redirect(BASE_URL . '/admin/banners.php');
}

if (isset($_GET['delete'])) {
    wh_csrf_verify();
    $id = (int) $_GET['delete'];
    $banner = wh_get_banner($id, $businessId);
    if ($banner) {
        wh_execute('DELETE FROM banners WHERE id=? AND business_id=?', 'ii', [$id, $businessId]);
        if ($banner['background_image']) {
            @unlink(__DIR__ . '/../' . $banner['background_image']);
        }
        wh_flash_set('success', 'Banner deleted.');
    }
    wh_redirect(BASE_URL . '/admin/banners.php');
}
if (isset($_GET['toggle'])) {
    wh_csrf_verify();
    $id = (int) $_GET['toggle'];
    wh_execute("UPDATE banners SET status = IF(status='active','inactive','active') WHERE id=? AND business_id=?", 'ii', [$id, $businessId]);
    wh_redirect(BASE_URL . '/admin/banners.php');
}

$banners = wh_get_banners($businessId, false);

$pageTitle = 'Homepage Banners';
$activePage = 'banners';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>Homepage Hero Carousel</h3>
    <button type="button" class="btn btn-primary btn-sm" onclick="openBannerModal()"><i class="fa-solid fa-plus"></i> Add Banner</button>
  </div>
  <p class="hint">Each active banner becomes one slide in the homepage carousel, in this sort order. Slides without an uploaded image fall back to a default photo so the homepage never looks broken.</p>
  <div class="table-scroll">
  <table class="admin-table">
    <thead><tr><th>Preview</th><th>Title</th><th>CTA</th><th>Order</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($banners as $b): ?>
      <tr>
        <td>
          <?php if ($b['background_image']): ?>
            <img src="<?= e(BASE_URL . '/' . $b['background_image']) ?>" style="width:90px;height:50px;object-fit:cover;border-radius:6px;">
          <?php else: ?>
            <span class="hint">Default image</span>
          <?php endif; ?>
        </td>
        <td><strong><?= e($b['title']) ?></strong><br><span class="hint"><?= e(mb_strimwidth($b['subheading'] ?? '', 0, 60, '…')) ?></span></td>
        <td><?= e($b['cta_text'] ?: '—') ?><br><span class="hint"><?= e($b['cta_link'] ?: '') ?></span></td>
        <td><?= (int) $b['sort_order'] ?></td>
        <td><span class="badge badge-<?= e($b['status']) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
        <td style="white-space:nowrap;">
          <button type="button" class="btn btn-light btn-sm" onclick='openBannerModal(<?= json_encode($b) ?>)'><i class="fa-solid fa-pen"></i></button>
          <a href="<?= e(BASE_URL) ?>/admin/banners.php?toggle=<?= (int) $b['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-power-off"></i></a>
          <a href="<?= e(BASE_URL) ?>/admin/banners.php?delete=<?= (int) $b['id'] ?>&csrf_token=<?= e(wh_csrf_token()) ?>" class="btn btn-danger btn-sm" data-confirm="Delete this banner?"><i class="fa-solid fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$banners): ?><tr><td colspan="6">No banners yet — the homepage will show a default hero until you add one.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal-overlay" id="bannerModal">
  <div class="modal">
    <div class="modal-head"><h3 id="bannerModalTitle">Add Banner</h3><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="post" enctype="multipart/form-data">
      <?= wh_csrf_field() ?>
      <input type="hidden" name="id" id="bannerId">
      <div class="form-group"><label>Heading Title *</label><input type="text" name="title" id="bannerTitle" required maxlength="200"></div>
      <div class="form-group"><label>Sub Heading</label><textarea name="subheading" id="bannerSubheading" rows="2" maxlength="300"></textarea></div>
      <div class="form-group"><label>Background Image</label><input type="file" name="background_image" accept="image/jpeg,image/png,image/webp">
        <p class="hint">Recommended: a wide landscape photo, at least 1600px wide. Leave blank on edit to keep the current image.</p>
      </div>
      <div class="form-grid">
        <div class="form-group"><label>Button Text</label><input type="text" name="cta_text" id="bannerCtaText" placeholder="e.g. Book Now"></div>
        <div class="form-group"><label>Button Link</label><input type="text" name="cta_link" id="bannerCtaLink" placeholder="/booking"></div>
      </div>
      <div class="form-grid">
        <div class="form-group"><label>Sort Order</label><input type="number" name="sort_order" id="bannerOrder" value="0"></div>
        <div class="form-group"><label>Status</label><select name="status" id="bannerStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Save Banner</button>
    </form>
  </div>
</div>
<script>
function openBannerModal(b) {
  document.getElementById('bannerModalTitle').textContent = b ? 'Edit Banner' : 'Add Banner';
  document.getElementById('bannerId').value = b ? b.id : '';
  document.getElementById('bannerTitle').value = b ? b.title : '';
  document.getElementById('bannerSubheading').value = b ? b.subheading : '';
  document.getElementById('bannerCtaText').value = b ? b.cta_text : '';
  document.getElementById('bannerCtaLink').value = b ? b.cta_link : '';
  document.getElementById('bannerOrder').value = b ? b.sort_order : 0;
  document.getElementById('bannerStatus').value = b ? b.status : 'active';
  document.getElementById('bannerModal').classList.add('open');
}
</script>
<?php require __DIR__ . '/footer.php'; ?>
