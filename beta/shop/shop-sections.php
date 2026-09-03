<?php
require __DIR__ . '/../config/config.php';
require_permission('manage_shop_sections');
$shopId = active_shop_id();

$sectionTypes = ['featured_products' => 'Featured Products', 'latest_products' => 'New Arrivals', 'best_selling' => 'Best Sellers',
    'discount_products' => 'Discount Products', 'category_grid' => 'Category Grid', 'custom' => 'Custom Product Carousel'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'];
    if ($action === 'add') {
        $sectionId = db_insert("INSERT INTO shop_sections (shop_id, section_type, heading, sort_order) VALUES (?,?,?,?)",
            'issi', [$shopId, $_POST['section_type'], trim($_POST['heading']), (int)$_POST['sort_order']]);
        if (!empty($_POST['product_ids'])) {
            foreach ($_POST['product_ids'] as $i => $pid) db_insert("INSERT INTO shop_section_products (shop_section_id, product_id, sort_order) VALUES (?,?,?)", 'iii', [$sectionId, (int)$pid, $i]);
        }
        flash('success', 'Section added.');
    } elseif ($action === 'delete') {
        db_exec("DELETE FROM shop_sections WHERE id=? AND shop_id=?", 'ii', [(int)$_POST['id'], $shopId]);
        flash('success', 'Section removed.');
    } elseif ($action === 'toggle') {
        db_exec("UPDATE shop_sections SET status = IF(status='active','inactive','active') WHERE id=? AND shop_id=?", 'ii', [(int)$_POST['id'], $shopId]);
    } elseif ($action === 'reorder') {
        foreach ($_POST['order'] as $sortOrder => $sectionId) db_exec("UPDATE shop_sections SET sort_order=? WHERE id=? AND shop_id=?", 'iii', [$sortOrder, (int)$sectionId, $shopId]);
        echo json_encode(['success' => true]); exit;
    }
    redirect(shop_url('shop-sections.php'));
}

$sections = db_fetch_all("SELECT * FROM shop_sections WHERE shop_id=? ORDER BY sort_order", 'i', [$shopId]);
foreach ($sections as &$s) {
    $s['products'] = db_fetch_all("SELECT p.id, p.name FROM shop_section_products ssp JOIN products p ON p.id=ssp.product_id WHERE ssp.shop_section_id=? ORDER BY ssp.sort_order", 'i', [$s['id']]);
}
unset($s);
$myProducts = db_fetch_all("SELECT id, name FROM products WHERE shop_id=? AND status='active' ORDER BY name", 'i', [$shopId]);

$dashRole = 'shop'; $pageTitle = 'Shop Sections';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Shop Sections (Builder)</h1></div>
<p class="text-muted">Design your shop's homepage by adding sections and choosing which products appear in each.</p>

<div class="dash-table-card" id="sections-list">
  <?php foreach ($sections as $s): ?>
    <div class="section-item" data-id="<?= $s['id'] ?>">
      <div class="section-item-head">
        <span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>
        <strong><?= clean($s['heading']) ?></strong> <span class="text-muted"><?= clean($sectionTypes[$s['section_type']] ?? $s['section_type']) ?></span>
        <span class="badge badge-<?= $s['status']==='active'?'success':'danger' ?>"><?= clean($s['status']) ?></span>
        <div class="section-item-actions">
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline">Toggle</button></form>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete section?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
        </div>
      </div>
      <div class="section-item-products"><?= $s['products'] ? implode(', ', array_column($s['products'], 'name')) : '<em>Uses automatic products</em>' ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$sections): ?><div class="empty-state"><h3>No sections yet</h3></div><?php endif; ?>
</div>

<div class="dash-form-card">
  <h3>Add Section</h3>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="form-row">
      <div><label>Section Type</label><select name="section_type"><?php foreach ($sectionTypes as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
      <div><label>Heading</label><input type="text" name="heading" required></div>
      <div><label>Sort Order</label><input type="number" name="sort_order" value="0"></div>
    </div>
    <label>Select Products (for Custom Carousel — leave empty for automatic sections)</label>
    <select name="product_ids[]" multiple size="8" class="product-multiselect">
      <?php foreach ($myProducts as $p): ?><option value="<?= $p['id'] ?>"><?= clean($p['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Add Section</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
