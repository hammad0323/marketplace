<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('inventory.manage');
if (is_post()) {
    require_csrf();
    $changes = 0;
    db_tx(function () use (&$changes) {
        foreach (input_array('qty') as $invId => $qty) {
            if (!is_numeric($qty)) {
                continue;
            }
            $row = db_one('SELECT * FROM product_inventory WHERE id = ? FOR UPDATE', [(int) $invId]);
            if (!$row || (int) $row['quantity'] === (int) $qty) {
                continue;
            }
            inventory_adjust((int) $row['product_id'], $row['variant_id'] !== null ? (int) $row['variant_id'] : null, (int) $qty - (int) $row['quantity'], 'manual', null, mb_substr(input('note') ?: 'Inventory screen', 0, 255));
            $changes++;
        }
    });
    audit_log('inventory_updated', 'inventory', null, ['rows' => $changes]);
    flash('success', $changes . ' stock level(s) updated.');
    admin_back('inventory');
}
$filter = input('filter', '', 'get');
$q = input('q', '', 'get');
$where = ["(v.id IS NULL OR v.is_active = 1)"];
$params = [];
if ($filter === 'low') {
    $where[] = 'p.track_stock = 1 AND i.quantity <= p.low_stock_threshold';
} elseif ($filter === 'out') {
    $where[] = 'p.track_stock = 1 AND i.quantity <= 0';
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR v.sku LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
$rows = db_all('SELECT i.*, p.name, p.sku psku, p.low_stock_threshold, p.track_stock, p.status, v.label, v.sku vsku FROM product_inventory i JOIN products p ON p.id = i.product_id LEFT JOIN product_variants v ON v.id = i.variant_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.name, v.sort_order LIMIT 500', $params);
$moves = db_all('SELECT m.*, p.name, v.label, a.name admin_name FROM inventory_movements m JOIN products p ON p.id = m.product_id LEFT JOIN product_variants v ON v.id = m.variant_id LEFT JOIN admins a ON a.id = m.admin_id ORDER BY m.id DESC LIMIT 25');
admin_header('Inventory', 'inventory');
?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get">
  <input class="form-control form-control-sm" style="width:220px" name="q" value="<?= e($q) ?>" placeholder="Search product or SKU">
  <select class="form-select form-select-sm" style="width:160px" name="filter"><option value="">All items</option><option value="low"<?= $filter === 'low' ? ' selected' : '' ?>>Low stock</option><option value="out"<?= $filter === 'out' ? ' selected' : '' ?>>Out of stock</option></select>
  <button class="btn btn-sm btn-outline-primary">Filter</button>
</form>
<form method="post"><?= csrf_field() ?>
<div class="card mb-4"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Product</th><th>Variant</th><th>SKU</th><th>Status</th><th>Threshold</th><th style="width:140px">On hand</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $low = $r['quantity'] <= $r['low_stock_threshold']; ?>
    <tr class="<?= $r['quantity'] <= 0 ? 'table-danger' : ($low ? 'table-warning' : '') ?>">
      <td><a href="<?= e(admin_url('product-edit', ['id' => $r['product_id']])) ?>#pricing"><?= e($r['name']) ?></a></td>
      <td><?= e($r['label'] ?: '—') ?></td><td><small><?= e($r['vsku'] ?: $r['psku']) ?></small></td><td><?= badge($r['status']) ?><?= $r['track_stock'] ? '' : ' <small class="text-muted">not tracked</small>' ?></td>
      <td><?= (int) $r['low_stock_threshold'] ?></td>
      <td><input class="form-control form-control-sm" type="number" step="1" name="qty[<?= (int) $r['id'] ?>]" value="<?= (int) $r['quantity'] ?>"></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Nothing to show.</td></tr><?php endif; ?>
  </tbody></table></div>
  <div class="card-body border-top d-flex gap-2"><input class="form-control form-control-sm" name="note" placeholder="Reason / note (e.g. stock take, new delivery)" maxlength="255" style="max-width:360px"><button class="btn btn-primary btn-sm">Save stock levels</button></div>
</div>
</form>
<div class="card"><div class="card-header">Recent stock movements</div><div class="table-responsive"><table class="table table-sm align-middle">
  <thead><tr><th>When</th><th>Product</th><th>Change</th><th>Balance</th><th>Reason</th><th>By</th></tr></thead>
  <tbody><?php foreach ($moves as $m): ?><tr><td><small><?= e(format_date($m['created_at'], true)) ?></small></td><td><?= e($m['name']) ?><?= $m['label'] ? ' — ' . e($m['label']) : '' ?></td><td class="<?= $m['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['change_qty'] > 0 ? '+' : '' ?><?= (int) $m['change_qty'] ?></td><td><?= (int) $m['balance'] ?></td><td><?= e(status_label($m['reason'])) ?><?= $m['order_id'] ? ' <a href="' . e(admin_url('order-view', ['id' => $m['order_id']])) . '">#</a>' : '' ?><?= $m['note'] ? '<br><small class="text-muted">' . e($m['note']) . '</small>' : '' ?></td><td><small><?= e($m['admin_name'] ?: 'System') ?></small></td></tr><?php endforeach; ?></tbody>
</table></div></div>
<?php admin_footer();
