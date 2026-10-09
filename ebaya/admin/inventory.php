<?php
/** Variant-level stock: adjustments, low-stock view, movement history. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('inventory.manage');

if (is_post()) {
    csrf_check();
    $vid = (int)post('variant_id');
    $mode = post('mode');
    $qty = (int)post('qty');
    $reason = in_list(post('reason'), ['adjustment', 'restock', 'correction', 'return'], 'adjustment');
    $note = mb_substr(post('note'), 0, 255) ?: null;
    try {
        db_tx(function () use ($vid, $mode, $qty, $reason, $note) {
            $cur = db_val('SELECT quantity FROM product_inventory WHERE variant_id = ? FOR UPDATE', [$vid]);
            if ($cur === null) {
                if (!db_val('SELECT id FROM product_variants WHERE id = ?', [$vid])) throw new InvalidArgumentException('Variant not found.');
                $cur = 0;
            }
            $delta = $mode === 'set' ? $qty - (int)$cur : $qty;
            if ($delta === 0) throw new InvalidArgumentException('No change to record.');
            $bal = inventory_change($vid, $delta, $reason, 'manual', null, $note);
            if ($bal < 0) throw new InvalidArgumentException('Stock cannot go below zero.');
        });
        if (post('threshold') !== '') db_exec('UPDATE product_inventory SET low_stock_threshold = ? WHERE variant_id = ?', [max(0, (int)post('threshold')), $vid]);
        audit('stock_adjust', 'variant', $vid, ['mode' => $mode, 'qty' => $qty, 'reason' => $reason, 'note' => $note]);
        flash('success', 'Stock updated.');
    } catch (InvalidArgumentException $e) {
        flash('danger', $e->getMessage());
    }
    redirect(admin_url('inventory' . query_with([])));
}

$q = mb_substr(get('q'), 0, 100);
$low = get('low') === '1';
$where = ['p.track_inventory = 1'];
$params = [];
if ($q !== '') { $where[] = '(p.name LIKE ? OR v.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($low) $where[] = 'COALESCE(i.quantity,0) <= COALESCE(i.low_stock_threshold, p.low_stock_threshold)';
$w = implode(' AND ', $where);
$base = "FROM product_variants v JOIN products p ON p.id = v.product_id LEFT JOIN product_inventory i ON i.variant_id = v.id WHERE $w";
$pg = paginate((int)db_val("SELECT COUNT(*) $base", $params), 40, (int)get('page', 1));
$rows = db_all("SELECT v.id, v.sku, v.status, p.id pid, p.name, p.status pstatus, COALESCE(i.quantity,0) qty, COALESCE(i.low_stock_threshold, p.low_stock_threshold) th, i.low_stock_threshold own_th $base ORDER BY p.name, v.sort_order LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$labels = [];
foreach ($rows as $r) $labels[$r['id']] = variant_label((int)$r['id']);
$moves = db_all('SELECT m.*, v.sku, a.name admin FROM inventory_movements m JOIN product_variants v ON v.id = m.variant_id LEFT JOIN admins a ON a.id = m.admin_id ' . (get('variant') ? 'WHERE m.variant_id = ' . (int)get('variant') : '') . ' ORDER BY m.id DESC LIMIT 50');

$admin_title = 'Inventory';
require __DIR__ . '/partials/header.php';
?>
<form class="d-flex gap-2 mb-3" method="get">
  <input class="form-control form-control-sm" style="width:240px" name="q" value="<?= e($q) ?>" placeholder="Search product or SKU">
  <label class="form-check align-self-center small ms-2"><input class="form-check-input" type="checkbox" name="low" value="1"<?= $low ? ' checked' : '' ?>> Low / out of stock only</label>
  <button class="btn btn-sm btn-outline-secondary">Filter</button>
</form>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th>Product / variant</th><th>SKU</th><th class="text-end">In stock</th><th>Alert at</th><th style="width:480px">Adjust</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['qty'] <= 0 ? 'table-danger' : ($r['qty'] <= $r['th'] ? 'table-warning' : '') ?>">
      <td><a href="<?= e(admin_url('product-edit?id=' . $r['pid'])) ?>"><?= e($r['name']) ?></a><div class="small text-muted"><?= e($labels[$r['id']] ?: 'Default') ?><?= $r['status'] === 'inactive' ? ' · inactive' : '' ?></div></td>
      <td class="small"><?= e($r['sku']) ?></td>
      <td class="text-end fw-semibold"><?= (int)$r['qty'] ?></td>
      <td class="small"><?= (int)$r['th'] ?></td>
      <td><form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="variant_id" value="<?= (int)$r['id'] ?>">
        <select name="mode" class="form-select form-select-sm" style="width:95px"><option value="add">Add/remove</option><option value="set">Set to</option></select>
        <input type="number" name="qty" class="form-control form-control-sm" style="width:75px" required placeholder="±qty">
        <select name="reason" class="form-select form-select-sm" style="width:110px"><option value="restock">Restock</option><option value="adjustment">Adjustment</option><option value="correction">Correction</option><option value="return">Return</option></select>
        <input name="note" class="form-control form-control-sm" placeholder="Note">
        <input type="number" name="threshold" class="form-control form-control-sm" style="width:70px" placeholder="Alert" value="<?= e($r['own_th']) ?>" title="Variant low-stock threshold">
        <button class="btn btn-sm btn-primary">Save</button></form></td>
      <td><a class="small" href="?variant=<?= (int)$r['id'] ?>#history">History</a></td>
    </tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-muted text-center py-4">No tracked variants found.</td></tr><?php endif; ?>
  </tbody>
</table></div></div>
<?= admin_pager($pg) ?>
<div class="card" id="history"><div class="card-header">Inventory movements <?= get('variant') ? '(selected variant) · <a href="' . e(admin_url('inventory')) . '">show all</a>' : '(latest 50)' ?></div>
  <div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Date</th><th>SKU</th><th>Change</th><th>Balance</th><th>Reason</th><th>Reference</th><th>Note</th><th>By</th></tr></thead>
    <tbody><?php foreach ($moves as $m): ?>
      <tr><td class="small"><?= e($m['created_at']) ?></td><td class="small"><?= e($m['sku']) ?></td><td class="<?= $m['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['change_qty'] > 0 ? '+' : '' ?><?= (int)$m['change_qty'] ?></td><td><?= (int)$m['balance_after'] ?></td>
        <td><?= e($m['reason']) ?></td><td class="small"><?= $m['reference_type'] === 'order' ? '<a href="' . e(admin_url('order-view?id=' . $m['reference_id'])) . '">Order #' . (int)$m['reference_id'] . '</a>' : e($m['reference_type']) ?></td><td class="small"><?= e($m['note']) ?></td><td class="small"><?= e($m['admin'] ?? 'System') ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php require __DIR__ . '/partials/footer.php';
