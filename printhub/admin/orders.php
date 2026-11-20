<?php
/** Platform-wide order list (read-only for the admin; shops manage their own orders). */
require __DIR__ . '/_init.php';
$q = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$shopId = (int) ($_GET['shop'] ?? 0);
$where = ['1'];
$params = [];
if ($q !== '') { $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
if (isset(order_statuses()[$status])) { $where[] = 'o.status = ?'; $params[] = $status; }
if ($shopId) { $where[] = 'o.shop_id = ?'; $params[] = $shopId; }
$w = implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM orders o WHERE $w", $params);
$pg = paginate($total, 30);
$rows = db_all("SELECT o.*, s.name shop_name, s.shop_code, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) items FROM orders o JOIN shops s ON s.id = o.shop_id WHERE $w ORDER BY o.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$shops = db_all('SELECT id, name FROM shops WHERE deleted_at IS NULL ORDER BY name');
admin_header('Orders');
?>
<form class="p-filters" method="get">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Order number, customer, phone">
    <select class="form-select" name="shop"><option value="">All shops</option><?php foreach ($shops as $s): ?><option value="<?= (int) $s['id'] ?>"<?= $shopId === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
    <select class="form-select" name="status"><option value="">All statuses</option><?php foreach (order_statuses() as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
    <button class="btn btn-outline-primary">Filter</button><span class="ms-auto align-self-center small text-muted"><?= number_format($total) ?> orders</span>
</form>
<div class="p-card"><div class="table-responsive"><table class="table p-table"><thead><tr><th>Order</th><th>Shop</th><th>Customer</th><th>Items</th><th>Status</th><th>Payment</th><th class="text-end">Amount</th><th>Placed</th></tr></thead><tbody>
<?php foreach ($rows as $o): ?>
<tr><td><strong><?= e($o['order_number']) ?></strong><div class="small text-muted"><?= $o['source'] === 'app' ? 'App' : 'Web' ?> · <?= $o['pricing_mode'] === 'auto' ? 'auto price' : 'quote' ?></div></td>
<td><a href="<?= e(path('admin/shop.php?id=' . $o['shop_id'])) ?>"><?= e($o['shop_name']) ?></a></td><td><?= e($o['customer_name']) ?><div class="small text-muted"><?= e($o['customer_phone']) ?></div></td>
<td><?= (int) $o['items'] ?></td><td><?= order_badge($o['status']) ?></td><td class="small"><?= e(str_replace('_', ' ', $o['payment_status'])) ?></td>
<td class="text-end"><?= order_amount($o) !== null ? e(money(order_amount($o))) : '—' ?></td><td class="small text-muted"><?= e(format_datetime($o['created_at'])) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="8"><div class="p-empty"><i class="bi bi-inbox"></i>No orders found.</div></td></tr><?php endif; ?>
</tbody></table></div></div>
<?= pagination_html($pg) ?>
<p class="small text-muted">Customer documents are private to each shop and are not viewable here.</p>
<?php admin_footer();
