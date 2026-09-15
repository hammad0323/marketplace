<?php
$pageTitle = 'Banners';
require_once __DIR__ . '/includes/admin_header.php';

$homepage = max(1, min(4, (int)($_GET['homepage'] ?? 1)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $hp = max(1, min(4, (int)$_POST['homepage']));
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $buttonText = trim($_POST['button_text'] ?? '');
        $buttonUrl = trim($_POST['button_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';
        $imgDesktop = handle_upload('image_desktop', 'banners');
        $imgMobile = handle_upload('image_mobile', 'banners');

        if ($id) {
            $sets = "homepage=?, title=?, subtitle=?, button_text=?, button_url=?, sort_order=?, status=?";
            $types = 'issssis';
            $params = [$hp, $title, $subtitle, $buttonText, $buttonUrl, $sortOrder, $status];
            if ($imgDesktop) { $sets .= ", image_desktop=?"; $types .= 's'; $params[] = $imgDesktop; }
            if ($imgMobile) { $sets .= ", image_mobile=?"; $types .= 's'; $params[] = $imgMobile; }
            $types .= 'i';
            $params[] = $id;
            $stmt = mysqli_prepare($mysqli, "UPDATE banners SET $sets WHERE id=?");
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Banner updated.');
        } else {
            if (!$imgDesktop) {
                flash_set('danger', 'Desktop image is required.');
            } else {
                $stmt = mysqli_prepare($mysqli, "INSERT INTO banners (homepage, title, subtitle, button_text, button_url, image_desktop, image_mobile, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'issssssis', $hp, $title, $subtitle, $buttonText, $buttonUrl, $imgDesktop, $imgMobile, $sortOrder, $status);
                mysqli_stmt_execute($stmt);
                flash_set('success', 'Banner created.');
            }
        }
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM banners WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Banner deleted.');
    } elseif ($action === 'toggle') {
        mysqli_query($mysqli, "UPDATE banners SET status = IF(status='active','inactive','active') WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Status updated.');
    }
    redirect('banners.php?homepage=' . $homepage);
}

$stmt = mysqli_prepare($mysqli, "SELECT * FROM banners WHERE homepage = ? ORDER BY sort_order ASC");
mysqli_stmt_bind_param($stmt, 'i', $homepage);
mysqli_stmt_execute($stmt);
$banners = mysqli_stmt_get_result($stmt);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="page-title">Banners</h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#bannerModal" onclick="resetBannerForm()"><i class="bi bi-plus-lg"></i> Add Banner</button>
</div>

<ul class="nav nav-pills mb-4">
  <?php for ($i = 1; $i <= 4; $i++): ?>
    <li class="nav-item"><a class="nav-link <?= $homepage == $i ? 'active' : '' ?>" href="?homepage=<?= $i ?>">Home <?= $i ?></a></li>
  <?php endfor; ?>
</ul>

<div class="row g-3">
<?php while ($b = mysqli_fetch_assoc($banners)): ?>
  <div class="col-md-6">
    <div class="admin-card">
      <img src="<?= e(BASE_URL.'/'.$b['image_desktop']) ?>" class="w-100 mb-2" style="height:160px;object-fit:cover;border-radius:8px">
      <h3 class="h6 mb-1"><?= e($b['title']) ?: '<em class="text-muted">No title</em>' ?></h3>
      <p class="small text-muted mb-2"><?= e($b['subtitle']) ?></p>
      <div class="d-flex justify-content-between align-items-center">
        <span class="badge <?= $b['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($b['status']) ?></span>
        <div>
          <button class="btn btn-sm btn-outline-secondary" onclick='editBanner(<?= json_encode($b) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-toggle2-on"></i></button></form>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </div>
      </div>
    </div>
  </div>
<?php endwhile; ?>
</div>

<div class="modal fade" id="bannerModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="b_id">
        <input type="hidden" name="homepage" value="<?= $homepage ?>">
        <div class="modal-header"><h5 class="modal-title" id="bannerModalTitle">Add Banner</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Heading</label><input type="text" name="title" id="b_title" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Subheading</label><input type="text" name="subtitle" id="b_subtitle" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Button Text</label><input type="text" name="button_text" id="b_button_text" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Button URL</label><input type="text" name="button_url" id="b_button_url" class="form-control" placeholder="/shop.php"></div>
            <div class="col-md-6"><label class="form-label">Desktop Image</label><input type="file" name="image_desktop" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Mobile Image</label><input type="file" name="image_mobile" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="b_sort" class="form-control" value="0"></div>
            <div class="col-md-6"><label class="form-label">Status</label>
              <select name="status" id="b_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary text-white">Save Banner</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function resetBannerForm(){document.getElementById('bannerModalTitle').textContent='Add Banner';['id','title','subtitle','button_text','button_url'].forEach(function(f){document.getElementById('b_'+f).value='';});document.getElementById('b_sort').value=0;document.getElementById('b_status').value='active';}
function editBanner(b){
  document.getElementById('bannerModalTitle').textContent='Edit Banner';
  document.getElementById('b_id').value=b.id;
  document.getElementById('b_title').value=b.title||'';
  document.getElementById('b_subtitle').value=b.subtitle||'';
  document.getElementById('b_button_text').value=b.button_text||'';
  document.getElementById('b_button_url').value=b.button_url||'';
  document.getElementById('b_sort').value=b.sort_order;
  document.getElementById('b_status').value=b.status;
  new bootstrap.Modal(document.getElementById('bannerModal')).show();
}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
