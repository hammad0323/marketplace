<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)$_POST['id']; $action = $_POST['action'];
    $product = db_fetch_one("SELECT * FROM products WHERE id=?", 'i', [$id]);
    if ($product) {
        if ($action === 'approve') db_exec("UPDATE products SET status='active' WHERE id=?", 'i', [$id]);
        if ($action === 'reject') db_exec("UPDATE products SET status='rejected', rejection_reason=? WHERE id=?", 'si', [trim($_POST['reason'] ?? 'Not approved'), $id]);
        if ($action === 'disable') db_exec("UPDATE products SET status='inactive' WHERE id=?", 'i', [$id]);
        if ($action === 'enable') db_exec("UPDATE products SET status='active' WHERE id=?", 'i', [$id]);
        if ($action === 'delete') db_exec("DELETE FROM products WHERE id=?", 'i', [$id]);
        audit_log('admin', current_admin()['id'], current_admin()['name'], ucfirst($action) . ' product', 'products', $id, $product['name']);
    }
    flash('success', 'Product updated.');
    redirect(admin_url('products/index.php?status=' . ($_GET['status'] ?? '')));
}

$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$where = ['1=1']; $params = []; $types = '';
if ($statusFilter) { $where[] = 'p.status = ?'; $types .= 's'; $params[] = $statusFilter; }
if ($search) { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $types .= 'ss'; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSql = implode(' AND ', $where);

$count = db_fetch_one("SELECT COUNT(*) c FROM products p WHERE $whereSql", $types, $params);
$pagination = paginate((int)$count['c'], 20);
$products = db_fetch_all("SELECT p.*, s.shop_name FROM products p JOIN shops s ON s.id=p.shop_id
    WHERE $whereSql ORDER BY p.created_at DESC LIMIT ? OFFSET ?", $types . 'ii', [...$params, $pagination['perPage'], $pagination['offset']]);

$dashRole = 'admin'; $pageTitle = 'Products'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Products</h1>
  <form class="table-search"><input type="hidden" name="status" value="<?= clean($statusFilter) ?>"><input type="text" name="search" placeholder="Search..." value="<?= clean($search) ?>"><button><i class="fa-solid fa-search"></i></button></form>
</div>
<div class="filter-tabs">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'rejected' => 'Rejected', 'inactive' => 'Inactive'] as $k => $v): ?>
    <a class="<?= $statusFilter === $k ? 'active' : '' ?>" href="<?= admin_url('products/index.php?status=' . $k) ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Image</th><th>Name</th><th>Shop</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($products): foreach ($products as $p): ?>
      <tr>
        <td><img class="table-thumb" src="<?= product_image_or_default($p['main_image']) ?>"></td>
        <td><?= clean($p['name']) ?></td>
        <td><?= clean($p['shop_name']) ?></td>
        <td><?= format_price($p['sale_price'] ?? $p['regular_price']) ?></td>
        <td><?= (int)$p['stock_quantity'] ?></td>
        <td><span class="badge badge-<?= $p['status'] === 'active' ? 'success' : ($p['status'] === 'pending' ? 'warn' : 'danger') ?>"><?= clean($p['status']) ?></span></td>
        <td class="action-cell">
          <?php if ($p['status'] === 'pending'): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
              <button name="action" value="reject" class="btn btn-sm btn-danger">Reject</button></form>
          <?php elseif ($p['status'] === 'active'): ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button name="action" value="disable" class="btn btn-sm btn-outline">Disable</button></form>
          <?php else: ?>
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button name="action" value="enable" class="btn btn-sm btn-success">Enable</button></form>
          <?php endif; ?>
          <a href="<?= base_url('product.php?slug=' . $p['slug']) ?>" target="_blank" class="btn btn-sm btn-outline">View</a>
          <form method="post" class="inline-form" onsubmit="return confirm('Delete this product permanently?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><button name="action" value="delete" class="btn btn-sm btn-danger">Delete</button></form>
        </td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="7"><div class="empty-state"><h3>No products found</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, admin_url('products/index.php?status=' . $statusFilter)) ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
