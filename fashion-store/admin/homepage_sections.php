<?php
$pageTitle = 'Homepage Sections';
require_once __DIR__ . '/includes/admin_header.php';

$homepage = max(1, min(10, (int)($_GET['homepage'] ?? 1)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $hp = max(1, min(10, (int)$_POST['homepage']));
        $sectionType = $_POST['section_type'] ?? 'products';
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $productFilter = $_POST['product_filter'] ?: null;
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $layout = trim($_POST['layout'] ?? '');
        $buttonText = trim($_POST['button_text'] ?? '');
        $buttonUrl = trim($_POST['button_url'] ?? '');
        $customHtml = $_POST['custom_html'] ?? '';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = $_POST['status'] === 'active' ? 'active' : 'inactive';

        if ($id) {
            $stmt = mysqli_prepare($mysqli, "UPDATE homepage_sections SET homepage=?, section_type=?, title=?, subtitle=?, product_filter=?, category_id=?, layout=?, button_text=?, button_url=?, custom_html=?, sort_order=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'issssissssisi', $hp, $sectionType, $title, $subtitle, $productFilter, $categoryId, $layout, $buttonText, $buttonUrl, $customHtml, $sortOrder, $status, $id);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Section updated.');
        } else {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO homepage_sections (homepage, section_type, title, subtitle, product_filter, category_id, layout, button_text, button_url, custom_html, sort_order, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'issssissssis', $hp, $sectionType, $title, $subtitle, $productFilter, $categoryId, $layout, $buttonText, $buttonUrl, $customHtml, $sortOrder, $status);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Section created.');
        }
    } elseif ($action === 'delete') {
        mysqli_query($mysqli, "DELETE FROM homepage_sections WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Section deleted.');
    } elseif ($action === 'toggle') {
        mysqli_query($mysqli, "UPDATE homepage_sections SET status = IF(status='active','inactive','active') WHERE id = " . (int)$_POST['id']);
        flash_set('success', 'Status updated.');
    } elseif ($action === 'move') {
        $id = (int)$_POST['id'];
        $dir = $_POST['dir'] === 'up' ? -1 : 1;
        $cur = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM homepage_sections WHERE id = $id"));
        if ($cur) {
            $op = $dir === -1 ? '<' : '>';
            $orderDir = $dir === -1 ? 'DESC' : 'ASC';
            $neighbor = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM homepage_sections WHERE homepage = {$cur['homepage']} AND sort_order $op {$cur['sort_order']} ORDER BY sort_order $orderDir LIMIT 1"));
            if ($neighbor) {
                mysqli_query($mysqli, "UPDATE homepage_sections SET sort_order = {$neighbor['sort_order']} WHERE id = $id");
                mysqli_query($mysqli, "UPDATE homepage_sections SET sort_order = {$cur['sort_order']} WHERE id = {$neighbor['id']}");
            }
        }
    }
    redirect('homepage_sections.php?homepage=' . $homepage);
}

$stmt = mysqli_prepare($mysqli, "SELECT s.*, c.name AS category_name FROM homepage_sections s LEFT JOIN categories c ON c.id = s.category_id WHERE s.homepage = ? ORDER BY s.sort_order ASC");
mysqli_stmt_bind_param($stmt, 'i', $homepage);
mysqli_stmt_execute($stmt);
$sections = mysqli_stmt_get_result($stmt);

$categories = mysqli_query($mysqli, "SELECT id, name FROM categories ORDER BY name");
$categoryList = [];
while ($c = mysqli_fetch_assoc($categories)) $categoryList[] = $c;

$sectionTypeLabels = [
    'categories' => 'Featured Categories',
    'products' => 'Product Collection',
    'promo_banner' => 'Promotional Banner',
    'image_text' => 'Image + Text Split',
    'brand_story' => 'Brand Story',
    'text_banner' => 'Text Banner',
    'two_column' => 'Two Column',
    'brands' => 'Brand Strip',
    'features' => 'Features / Overview',
    'counters' => 'Stats / Counters',
    'newsletter' => 'Newsletter Signup',
    'testimonials' => 'Testimonials',
    'instagram' => 'Instagram / Social Feed',
    'custom_html' => 'Custom HTML Block',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="page-title">Homepage Sections</h1>
  <button class="btn btn-primary text-white" data-bs-toggle="modal" data-bs-target="#secModal" onclick="resetSecForm()"><i class="bi bi-plus-lg"></i> Add Section</button>
</div>

<ul class="nav nav-pills mb-4">
  <?php for ($i = 1; $i <= 10; $i++): ?>
    <li class="nav-item"><a class="nav-link <?= $homepage == $i ? 'active' : '' ?>" href="?homepage=<?= $i ?>">Home <?= $i ?></a></li>
  <?php endfor; ?>
</ul>

<div class="admin-card">
  <table class="table table-hover align-middle">
    <thead><tr><th></th><th>Type</th><th>Title</th><th>Filter / Layout</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php while ($s = mysqli_fetch_assoc($sections)): ?>
      <tr>
        <td class="text-nowrap">
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="up"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light"><i class="bi bi-arrow-up"></i></button></form>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="down"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light"><i class="bi bi-arrow-down"></i></button></form>
        </td>
        <td><?= e($sectionTypeLabels[$s['section_type']] ?? $s['section_type']) ?></td>
        <td><?= e($s['title']) ?: '<em class="text-muted">—</em>' ?></td>
        <td class="small text-muted"><?= e($s['product_filter'] ?: ($s['category_name'] ?: $s['layout'])) ?></td>
        <td><span class="badge <?= $s['status']==='active'?'text-bg-success':'text-bg-secondary' ?>"><?= e($s['status']) ?></span></td>
        <td class="text-end text-nowrap">
          <a href="homepage_section_items.php?section_id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-primary">Items</a>
          <button class="btn btn-sm btn-outline-secondary" onclick='editSec(<?= json_encode($s) ?>)'><i class="bi bi-pencil"></i></button>
          <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-toggle2-on"></i></button></form>
          <form method="post" class="d-inline confirm-delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="secModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="s_id">
        <input type="hidden" name="homepage" value="<?= $homepage ?>">
        <div class="modal-header"><h5 class="modal-title" id="secModalTitle">Add Section</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Section Type</label>
              <select name="section_type" id="s_type" class="form-select">
                <?php foreach ($sectionTypeLabels as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Layout</label>
              <select name="layout" id="s_layout" class="form-select">
                <option value="grid-3">Grid — 3 columns</option>
                <option value="grid-4">Grid — 4 columns</option>
                <option value="slider">Slider</option>
                <option value="split-left">Split — Image Left</option>
                <option value="split-right">Split — Image Right</option>
                <option value="two-column">Two Column</option>
                <option value="full-width">Full Width</option>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Title</label><input type="text" name="title" id="s_title" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Subtitle</label><input type="text" name="subtitle" id="s_subtitle" class="form-control"></div>
            <div class="col-md-6">
              <label class="form-label">Product Filter (for Product Collection)</label>
              <select name="product_filter" id="s_filter" class="form-select">
                <option value="">— None —</option>
                <option value="featured">Featured</option>
                <option value="new_arrival">New Arrivals</option>
                <option value="best_seller">Best Sellers</option>
                <option value="trending">Trending</option>
                <option value="sale">On Sale</option>
                <option value="category">By Category</option>
                <option value="manual">Manual Selection (use Items)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category (if "By Category")</label>
              <select name="category_id" id="s_category" class="form-select select2">
                <option value="">— None —</option>
                <?php foreach ($categoryList as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Button Text</label><input type="text" name="button_text" id="s_btn_text" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Button URL</label><input type="text" name="button_url" id="s_btn_url" class="form-control"></div>
            <div class="col-12"><label class="form-label">Custom HTML (for Custom HTML Block)</label><textarea name="custom_html" id="s_html" class="form-control" rows="3"></textarea></div>
            <div class="col-md-6"><label class="form-label">Sort Order</label><input type="number" name="sort_order" id="s_sort" class="form-control" value="0"></div>
            <div class="col-md-6"><label class="form-label">Status</label><select name="status" id="s_status" class="form-select"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary text-white">Save Section</button></div>
      </form>
    </div>
  </div>
</div>
<script>
function resetSecForm(){
  document.getElementById('secModalTitle').textContent='Add Section';
  document.getElementById('s_id').value='';
  ['title','subtitle','btn_text','btn_url','html'].forEach(function(f){document.getElementById('s_'+f).value='';});
  document.getElementById('s_type').value='products';
  document.getElementById('s_layout').value='grid-4';
  document.getElementById('s_filter').value='';
  document.getElementById('s_category').value='';
  document.getElementById('s_sort').value=0;
  document.getElementById('s_status').value='active';
}
function editSec(s){
  document.getElementById('secModalTitle').textContent='Edit Section';
  document.getElementById('s_id').value=s.id;
  document.getElementById('s_title').value=s.title||'';
  document.getElementById('s_subtitle').value=s.subtitle||'';
  document.getElementById('s_btn_text').value=s.button_text||'';
  document.getElementById('s_btn_url').value=s.button_url||'';
  document.getElementById('s_html').value=s.custom_html||'';
  document.getElementById('s_type').value=s.section_type;
  document.getElementById('s_layout').value=s.layout||'grid-4';
  document.getElementById('s_filter').value=s.product_filter||'';
  document.getElementById('s_category').value=s.category_id||'';
  document.getElementById('s_sort').value=s.sort_order;
  document.getElementById('s_status').value=s.status;
  new bootstrap.Modal(document.getElementById('secModal')).show();
}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
