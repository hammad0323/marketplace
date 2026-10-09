<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('products.view');

$q = input('q', '', 'get');
$cat = input_int('category', 0, 'get');
$status = input('status', '', 'get');
$stock = input('stock', '', 'get');
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.sku LIKE ?))';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
if ($cat) {
    $where[] = '(p.category_id = ? OR p.subcategory_id = ?)';
    array_push($params, $cat, $cat);
}
if (in_array($status, ['draft', 'published', 'inactive'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($stock === 'low') {
    $where[] = 'p.track_stock = 1 AND COALESCE(inv.qty, 0) <= p.low_stock_threshold';
} elseif ($stock === 'out') {
    $where[] = 'p.track_stock = 1 AND COALESCE(inv.qty, 0) <= 0';
}
$base = 'FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN (SELECT product_id, SUM(quantity) qty FROM product_inventory GROUP BY product_id) inv ON inv.product_id = p.id WHERE ' . implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) $base", $params);
$pg = paginate($total, 25, input_int('page', 1, 'get'));
$rows = db_all("SELECT p.*, c.name category_name, COALESCE(inv.qty, 0) stock_qty,
    (SELECT file_path FROM product_images i WHERE i.product_id = p.id ORDER BY is_main DESC, sort_order LIMIT 1) image,
    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) variant_count
    $base ORDER BY p.updated_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);

admin_header('Products', 'products');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="d-flex flex-wrap gap-2" method="get">
    <input class="form-control form-control-sm" style="width:220px" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU">
    <select class="form-select form-select-sm" style="width:200px" name="category"><option value="">All categories</option><?php foreach (category_options(false) as $id => $label): ?><option value="<?= (int) $id ?>"<?= $cat === (int) $id ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
    <select class="form-select form-select-sm" style="width:140px" name="status"><option value="">Any status</option><?php foreach (['published', 'draft', 'inactive'] as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <select class="form-select form-select-sm" style="width:140px" name="stock"><option value="">Any stock</option><option value="low"<?= $stock === 'low' ? ' selected' : '' ?>>Low stock</option><option value="out"<?= $stock === 'out' ? ' selected' : '' ?>>Out of stock</option></select>
    <button class="btn btn-sm btn-outline-primary">Filter</button>
  </form>
  <?php if (can('products.edit')): ?><a class="btn btn-primary btn-sm" href="<?= e(admin_url('product-edit')) ?>"><i class="bi bi-plus-lg"></i> Add product</a><?php endif; ?>
</div>
<form method="post" action="<?= e(admin_url('product-action')) ?>" data-confirm="Apply this action to the selected products?">
<?= csrf_field() ?>
<div class="card">
  <div class="table-responsive"><table class="table table-hover align-middle">
    <thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input" data-check-all=".row-check"></th><th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Flags</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): $pr = unit_pricing($p); ?>
      <tr>
        <td><input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= (int) $p['id'] ?>"></td>
        <td><img class="thumb" src="<?= e(media_url($p['image'])) ?>" alt=""></td>
        <td><a href="<?= e(admin_url('product-edit', ['id' => $p['id']])) ?>"><strong><?= e($p['name']) ?></strong></a><br><small class="text-muted"><?= e($p['sku']) ?><?= $p['variant_count'] ? ' · ' . (int) $p['variant_count'] . ' variants' : '' ?></small></td>
        <td><?= e($p['category_name']) ?></td>
        <td><?= e(money($pr['price'])) ?><?php if ($pr['on_sale']): ?><br><del class="small text-muted"><?= e(money($pr['regular'])) ?></del><?php endif; ?></td>
        <td><?php if ((int) $p['track_stock']): ?><span class="badge text-bg-<?= $p['stock_qty'] <= 0 ? 'danger' : ($p['stock_qty'] <= $p['low_stock_threshold'] ? 'warning' : 'light') ?>"><?= (int) $p['stock_qty'] ?></span><?php else: ?><small><?= e(status_label($p['stock_status'])) ?></small><?php endif; ?></td>
        <td><?= badge($p['status']) ?></td>
        <td class="small"><?= $p['is_featured'] ? '<span class="badge text-bg-light">Featured</span> ' : '' ?><?= $p['is_new_arrival'] ? '<span class="badge text-bg-light">New</span> ' : '' ?><?= $p['is_best_seller'] ? '<span class="badge text-bg-light">Best</span>' : '' ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-light" href="<?= e(path_url('product/' . $p['slug'])) ?>" target="_blank" title="View"><i class="bi bi-eye"></i></a>
          <?php if (can('products.edit')): ?><a class="btn btn-sm btn-light" href="<?= e(admin_url('product-edit', ['id' => $p['id']])) ?>" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if (can('products.edit')): ?>
  <div class="card-body d-flex flex-wrap gap-2 align-items-center border-top">
    <select name="action" class="form-select form-select-sm" style="width:200px" required>
      <option value="">Bulk action…</option><option value="publish">Publish</option><option value="draft">Set to draft</option><option value="inactive">Set inactive</option>
      <option value="duplicate">Duplicate</option><?php if (can('products.delete')): ?><option value="delete">Delete</option><?php endif; ?>
    </select>
    <button class="btn btn-sm btn-outline-primary">Apply</button>
    <div class="ms-auto"><?= admin_pager($pg) ?></div>
  </div>
  <?php endif; ?>
</div>
</form>
<?php admin_footer();
