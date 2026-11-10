<?php
require __DIR__ . '/includes/init.php';
admin_require('inventory');
$pid = input_int('product'); $type = input('type'); $from = input('from'); $to = input('to'); $dir = input('dir');
$where = ['1=1']; $params = [];
if ($pid) { $where[] = 'm.product_id = ?'; $params[] = $pid; }
if ($type !== '' && array_key_exists($type, movement_types())) { $where[] = 'm.movement_type = ?'; $params[] = $type; }
if ($dir === 'in') $where[] = 'm.qty_change > 0';
if ($dir === 'out') $where[] = 'm.qty_change < 0';
if ($from) { $where[] = 'm.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 'm.created_at <= ?'; $params[] = $to . ' 23:59:59'; }
$w = implode(' AND ', $where);
$sql = "FROM inventory_movements m JOIN products p ON p.id = m.product_id LEFT JOIN product_variations v ON v.id = m.variation_id LEFT JOIN admins a ON a.id = m.admin_id WHERE $w";
if (input('export') === '1') {
    $rows = db_all("SELECT m.created_at, p.name, v.name AS vname, m.movement_type, m.qty_change, m.balance_after, m.reason, a.name AS admin $sql ORDER BY m.id DESC LIMIT 20000", $params);
    csv_download('stock-history.csv', ['Date', 'Product', 'Variation', 'Type', 'Change', 'Balance', 'Reason', 'By'], array_map('array_values', $rows));
}
$pg = paginate((int) db_val("SELECT COUNT(*) $sql", $params), 50);
$rows = db_all("SELECT m.*, p.name, v.name AS vname, a.name AS admin_name $sql ORDER BY m.id DESC LIMIT 50 OFFSET {$pg['offset']}", $params);
$product = $pid ? db_row('SELECT name FROM products WHERE id = ?', [$pid]) : null;
admin_header('Stock History' . ($product ? ': ' . $product['name'] : ''), 'movements');
?>
<form class="a-filter">
  <?php if ($pid): ?><input type="hidden" name="product" value="<?= $pid ?>"><?php endif; ?>
  <?= a_sel('type', movement_types(), $type, 'All types') ?><?= a_sel('dir', ['in' => 'Stock in', 'out' => 'Stock out'], $dir, 'In & out') ?>
  <input type="date" name="from" class="form-control form-control-sm" value="<?= e($from) ?>" aria-label="From"><input type="date" name="to" class="form-control form-control-sm" value="<?= e($to) ?>" aria-label="To">
  <button class="btn btn-sm btn-light">Filter</button> <a href="<?= e(url_with(['export' => 1])) ?>" class="btn btn-sm btn-outline-secondary">Export CSV</a>
  <?php if ($pid): ?><a href="<?= aurl('movements.php') ?>" class="btn btn-sm btn-link">All products</a><?php endif; ?>
</form>
<div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
  <thead><tr><th>Date</th><th>Product</th><th>Type</th><th class="text-end">Change</th><th class="text-end">Balance</th><th>Reason / reference</th><th>By</th></tr></thead><tbody>
  <?php foreach ($rows as $m): ?>
  <tr><td class="small text-nowrap"><?= fmt_datetime($m['created_at']) ?></td><td><a href="?product=<?= (int) $m['product_id'] ?>"><?= e($m['name']) ?></a><?= $m['vname'] ? ' <small class="text-muted">' . e($m['vname']) . '</small>' : '' ?></td>
    <td><span class="badge bg-light text-dark border"><?= e(movement_types()[$m['movement_type']] ?? $m['movement_type']) ?></span></td>
    <td class="text-end fw-bold <?= $m['qty_change'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $m['qty_change'] > 0 ? '+' : '' ?><?= (int) $m['qty_change'] ?></td><td class="text-end"><?= (int) $m['balance_after'] ?></td>
    <td class="small"><?= e($m['reason']) ?><?php if ($m['reference_type'] === 'order' && $m['reference_id']): ?> <a href="<?= aurl('order-view.php?id=' . $m['reference_id']) ?>">view order</a><?php elseif ($m['reference_type'] === 'purchase'): ?> <a href="<?= aurl('purchases.php?view=' . $m['reference_id']) ?>">view purchase</a><?php endif; ?></td>
    <td class="small"><?= e($m['admin_name'] ?: 'System / customer') ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-center text-muted p-4">No stock movements.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
