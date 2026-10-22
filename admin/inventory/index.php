<?php
/** Stock levels for every product and variant, with quick adjustments. */
require __DIR__ . '/../includes/init.php';
admin_require('inventory');
if (is_post()) {
    $pid = input_int('product_id');
    $vid = input_int('variant_id') ?: null;
    $mode = input('mode', 'adjust', 'post');
    $qty = input_int('qty');
    $reason = in_array(input('reason', '', 'post'), ['manual', 'return', 'correction'], true) ? input('reason', '', 'post') : 'manual';
    $note = mb_substr(input('note', '', 'post'), 0, 255);
    $current = $vid ? db_val('SELECT stock_qty FROM product_variants WHERE id = ? AND product_id = ?', [$vid, $pid]) : db_val('SELECT stock_qty FROM products WHERE id = ?', [$pid]);
    if ($current === null) {
        flash('error', 'Item not found.');
    } else {
        $change = $mode === 'set' ? $qty - (int) $current : $qty;
        if ($change !== 0) {
            db_transaction(fn() => inventory_adjust($pid, $vid, $change, $reason, null, $note ?: null, admin_id()));
            audit_log('stock_adjusted', 'product', $pid, ['variant_id' => $vid, 'change' => $change, 'reason' => $reason, 'note' => $note]);
        }
        flash('success', 'Stock updated (' . ($change >= 0 ? '+' : '') . $change . ').');
    }
    redirect_back('admin/inventory/');
}
$q = input('q', '', 'get');
$filter = input('filter', '', 'get');
$params = [];
$sqlV = "SELECT p.id pid, p.name, p.status, v.id vid, v.sku, v.stock_qty, COALESCE(v.low_stock_threshold, p.low_stock_threshold) th, p.track_stock, p.allow_backorder, v.is_active,
         (SELECT GROUP_CONCAT(CONCAT(option_name, ': ', option_value) ORDER BY sort_order SEPARATOR ' / ') FROM product_variant_values WHERE variant_id = v.id) label
         FROM product_variants v JOIN products p ON p.id = v.product_id";
$sqlP = "SELECT p.id pid, p.name, p.status, NULL vid, p.sku, p.stock_qty, p.low_stock_threshold th, p.track_stock, p.allow_backorder, 1 is_active, NULL label FROM products p WHERE p.has_variants = 0";
$union = "SELECT * FROM ($sqlV UNION ALL $sqlP) x WHERE 1";
if ($q !== '') {
    $union .= ' AND (x.name LIKE ? OR x.sku LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
if ($filter === 'low') $union .= ' AND x.track_stock = 1 AND x.stock_qty <= x.th AND x.stock_qty > 0';
if ($filter === 'out') $union .= ' AND x.track_stock = 1 AND x.stock_qty <= 0';
$total = (int) db_val("SELECT COUNT(*) FROM ($union) t", $params);
$pg = paginate($total, 50);
$rows = db_all("$union ORDER BY x.name, x.sku LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
admin_header('Inventory');
?>
<form class="a-filters" method="get">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Product or SKU">
    <select class="form-select" name="filter"><option value="">All items</option><option value="low"<?= $filter === 'low' ? ' selected' : '' ?>>Low stock</option><option value="out"<?= $filter === 'out' ? ' selected' : '' ?>>Out of stock</option></select>
    <button class="btn btn-dark">Filter</button>
    <a class="btn btn-outline-dark ms-auto" href="<?= e(url('admin/inventory/movements.php')) ?>"><i class="bi bi-clock-history"></i> Stock movements</a>
</form>
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0">
<thead><tr><th>Product / variant</th><th>SKU</th><th>Stock</th><th>Status</th><th style="min-width:420px">Adjust</th></tr></thead><tbody>
<?php foreach ($rows as $r):
    $state = !(int) $r['track_stock'] ? ['Not tracked', 'secondary'] : ($r['stock_qty'] <= 0 ? [(int) $r['allow_backorder'] ? 'Backorder' : 'Out of stock', 'danger'] : ($r['stock_qty'] <= $r['th'] ? ['Low', 'warning'] : ['In stock', 'success'])); ?>
<tr>
    <td><a href="<?= e(url('admin/products/edit.php?id=' . $r['pid'])) ?>"><?= e($r['name']) ?></a><?php if ($r['label']): ?><div class="small text-muted"><?= e($r['label']) ?><?= !(int) $r['is_active'] ? ' · inactive' : '' ?></div><?php endif; ?></td>
    <td class="small"><?= e($r['sku']) ?></td>
    <td class="fw-bold"><?= (int) $r['stock_qty'] ?> <span class="small text-muted fw-normal">/ alert ≤ <?= (int) $r['th'] ?></span></td>
    <td><?= a_badge($state[0], $state[1]) ?></td>
    <td><form method="post" class="d-flex gap-1"><?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $r['pid'] ?>"><input type="hidden" name="variant_id" value="<?= (int) $r['vid'] ?>">
        <select class="form-select form-select-sm" name="mode" style="max-width:110px"><option value="adjust">Add / remove</option><option value="set">Set to</option></select>
        <input class="form-control form-control-sm" type="number" name="qty" required style="max-width:80px" placeholder="±qty">
        <select class="form-select form-select-sm" name="reason" style="max-width:120px"><option value="manual">Restock</option><option value="return">Return</option><option value="correction">Correction</option></select>
        <input class="form-control form-control-sm" name="note" placeholder="Note">
        <button class="btn btn-sm btn-dark">Save</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?= pagination_links($pg, url('admin/inventory/')) ?>
<?php admin_footer();
