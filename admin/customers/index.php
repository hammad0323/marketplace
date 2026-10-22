<?php
require __DIR__ . '/../includes/init.php';
admin_require('customers');
$q = input('q', '', 'get');
$where = '1';
$params = [];
if ($q !== '') {
    $where = '(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
$total = (int) db_val("SELECT COUNT(*) FROM customers c WHERE $where", $params);
$pg = paginate($total, 30);
$rows = db_all("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) orders,
    (SELECT COALESCE(SUM(o.grand_total - o.amount_refunded),0) FROM orders o WHERE o.customer_id = c.id AND " . sql_revenue_condition('o') . ") spent
    FROM customers c WHERE $where ORDER BY c.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
// Guest order lookup
$guests = [];
if ($q !== '') {
    $guests = db_all("SELECT email, phone, MAX(customer_name) name, COUNT(*) n, SUM(grand_total) total, MAX(created_at) last FROM orders WHERE customer_id IS NULL AND (email LIKE ? OR phone LIKE ? OR customer_name LIKE ? OR order_number LIKE ?) GROUP BY email, phone ORDER BY last DESC LIMIT 20",
        ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%']);
}
admin_header('Customers');
?>
<form class="a-filters" method="get"><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone (also searches guest orders)"><button class="btn btn-dark">Search</button></form>
<div class="card a-card"><div class="card-header"><?= number_format($total) ?> registered customers</div><div class="table-responsive"><table class="table a-table mb-0">
<thead><tr><th>Customer</th><th>Phone</th><th>Orders</th><th>Total spent</th><th>Joined</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $c): ?><tr><td><a href="<?= e(url('admin/customers/view.php?id=' . $c['id'])) ?>"><strong><?= e($c['first_name'] . ' ' . $c['last_name']) ?></strong></a><div class="small text-muted"><?= e($c['email']) ?></div></td>
<td><?= e($c['phone']) ?></td><td><?= (int) $c['orders'] ?></td><td><?= e(money($c['spent'])) ?></td><td class="small"><?= e(format_date($c['created_at'])) ?></td><td><?= a_status_toggle((bool) $c['is_active']) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?= pagination_links($pg, url('admin/customers/')) ?>
<?php if ($guests): ?>
<div class="card a-card"><div class="card-header">Guest orders matching “<?= e($q) ?>”</div><div class="table-responsive"><table class="table a-table mb-0"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total</th><th>Last order</th></tr></thead><tbody>
<?php foreach ($guests as $g): ?><tr><td><?= e($g['name']) ?></td><td><a href="<?= e(url('admin/orders/?q=' . urlencode($g['email']))) ?>"><?= e($g['email']) ?></a></td><td><?= e($g['phone']) ?></td><td><?= (int) $g['n'] ?></td><td><?= e(money($g['total'])) ?></td><td class="small"><?= e(format_date($g['last'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>
<?php admin_footer();
