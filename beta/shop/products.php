<?php
require __DIR__ . '/../config/config.php';
require_permission('manage_products');
$shopId = active_shop_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!staff_has_permission('delete_product')) { flash('error', 'You do not have permission to delete products.'); redirect(shop_url('products.php')); }
    csrf_verify();
    db_exec("DELETE FROM products WHERE id=? AND shop_id=?", 'ii', [(int)$_POST['id'], $shopId]);
    flash('success', 'Product deleted.');
    redirect(shop_url('products.php'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    csrf_verify();
    db_exec("UPDATE products SET status = IF(status='active','inactive','active') WHERE id=? AND shop_id=? AND status IN ('active','inactive')", 'ii', [(int)$_POST['id'], $shopId]);
    redirect(shop_url('products.php'));
}

$search = trim($_GET['search'] ?? '');
$where = "shop_id = ?"; $params = [$shopId]; $types = 'i';
if ($search) { $where .= " AND name LIKE ?"; $params[] = "%$search%"; $types .= 's'; }

$count = db_fetch_one("SELECT COUNT(*) c FROM products WHERE $where", $types, $params);
$pagination = paginate((int)$count['c'], 15);
$products = db_fetch_all("SELECT * FROM products WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?", $types . 'ii', [...$params, $pagination['perPage'], $pagination['offset']]);

$dashRole = current_shop_owner() ? 'shop' : 'employee'; $pageTitle = 'Products';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Products</h1>
  <?php if (staff_has_permission('add_product')): ?><a href="<?= shop_url('add-product.php') ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Product</a><?php endif; ?>
</div>
<form class="table-search"><input type="text" name="search" placeholder="Search products..." value="<?= clean($search) ?>"><button><i class="fa-solid fa-search"></i></button></form>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Image</th><th>Name</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($products): foreach ($products as $p): ?>
      <tr>
        <td><img class="table-thumb" src="<?= product_image_or_default($p['main_image']) ?>"></td>
        <td><?= clean($p['name']) ?></td>
        <td><?= format_price($p['sale_price'] ?? $p['regular_price']) ?></td>
        <td><?= (int)$p['stock_quantity'] ?></td>
        <td><span class="badge badge-<?= $p['status']==='active'?'success':($p['status']==='pending'?'warn':'danger') ?>"><?= clean($p['status']) ?></span></td>
        <td class="action-cell">
          <?php if (staff_has_permission('edit_product')): ?><a href="<?= shop_url('edit-product.php?id=' . $p['id']) ?>" class="btn btn-sm btn-outline">Edit</a><?php endif; ?>
          <?php if (in_array($p['status'], ['active','inactive'], true)): ?>
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-outline"><?= $p['status']==='active' ? 'Disable' : 'Enable' ?></button></form>
          <?php endif; ?>
          <?php if (staff_has_permission('delete_product')): ?>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this product?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-sm btn-danger">Delete</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-box-open"></i><h3>No Products Found</h3><p>You haven't added any products yet.</p></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, shop_url('products.php?search=' . urlencode($search))) ?>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
