<?php
require __DIR__ . '/../includes/init.php';
admin_require('inventory');
$params = [];
$where = '1';
if ($pid = input_int('product')) {
    $where .= ' AND m.product_id = ?';
    $params[] = $pid;
}
if (($r = input('reason', '', 'get')) !== '') {
    $where .= ' AND m.reason = ?';
    $params[] = $r;
}
$total = (int) db_val("SELECT COUNT(*) FROM product_inventory m WHERE $where", $params);
$pg = paginate($total, 50);
$rows = db_all("SELECT m.*, p.name, v.sku vsku, a.name admin_name FROM product_inventory m JOIN products p ON p.id = m.product_id LEFT JOIN product_variants v ON v.id = m.variant_id
    LEFT JOIN admins a ON a.id = m.admin_id WHERE $where ORDER BY m.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
admin_header('Stock movements');
?>
<form class="a-filters" method="get"><?php if ($pid): ?><input type="hidden" name="product" value="<?= $pid ?>"><?php endif; ?>
<select class="form-select" name="reason"><option value="">All reasons</option><?php foreach (['initial', 'manual', 'order', 'cancel', 'refund', 'return', 'correction'] as $x): ?><option<?= $r === $x ? ' selected' : '' ?>><?= $x ?></option><?php endforeach; ?></select>
<button class="btn btn-dark">Filter</button><?php if ($pid): ?><a href="<?= e(url('admin/inventory/movements.php')) ?>" class="btn btn-link">All products</a><?php endif; ?></form>
<div class="card a-card"><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>When</th><th>Product</th><th>SKU</th><th>Change</th><th>Balance</th><th>Reason</th><th>Reference</th><th>By</th></tr></thead><tbody>
<?php foreach ($rows as $m): ?><tr><td class="small text-nowrap"><?= e(format_datetime($m['created_at'])) ?></td><td><?= e($m['name']) ?></td><td class="small"><?= e($m['vsku'] ?? '—') ?></td>
<td class="fw-bold <?= $m['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $m['change_qty'] > 0 ? '+' : '' ?><?= (int) $m['change_qty'] ?></td><td><?= (int) $m['balance_after'] ?></td>
<td><?= e($m['reason']) ?><?= $m['note'] ? '<div class="small text-muted">' . e($m['note']) . '</div>' : '' ?></td><td class="small"><?= e($m['reference']) ?></td><td class="small"><?= e($m['admin_name'] ?? 'System') ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No movements.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= pagination_links($pg, url('admin/inventory/movements.php')) ?>
<?php admin_footer();
