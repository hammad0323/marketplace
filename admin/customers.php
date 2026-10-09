<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('customers.view');
$q = input('q', '', 'get');
$where = '1=1';
$params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where = '(c.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ? OR CONCAT(c.first_name, " ", c.last_name) LIKE ?)';
    $params = [$like, $like, $like, $like, $like];
}
$where = str_replace('" "', "' '", $where);
$total = (int) db_val("SELECT COUNT(*) FROM customers c WHERE $where", $params);
$pg = paginate($total, 30, input_int('page', 1, 'get'));
$rows = db_all("SELECT c.*, COUNT(o.id) orders, COALESCE(SUM(CASE WHEN o.status NOT IN ('cancelled','refunded') THEN o.grand_total - o.refunded_total END), 0) spent, MAX(o.created_at) last_order
    FROM customers c LEFT JOIN orders o ON o.customer_id = c.id WHERE $where GROUP BY c.id ORDER BY c.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
$guests = (int) db_val('SELECT COUNT(DISTINCT email) FROM orders WHERE customer_id IS NULL');
admin_header('Customers', 'customers');
?>
<form class="d-flex gap-2 mb-3" method="get"><input class="form-control form-control-sm" style="max-width:320px" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone"><button class="btn btn-sm btn-outline-primary">Search</button>
  <span class="ms-auto small text-muted align-self-center"><?= $total ?> registered · <?= $guests ?> guest checkout emails (see Orders)</span></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Customer</th><th>Contact</th><th>Orders</th><th>Total spent</th><th>Last order</th><th>Status</th><th>Joined</th></tr></thead>
  <tbody><?php foreach ($rows as $c): ?>
    <tr><td><a href="<?= e(admin_url('customer-view', ['id' => $c['id']])) ?>"><strong><?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?></strong></a></td>
      <td><?= e($c['email']) ?><br><small class="text-muted"><?= e($c['phone']) ?></small></td><td><?= (int) $c['orders'] ?></td><td><?= e(money($c['spent'])) ?></td>
      <td><small><?= e(format_date($c['last_order'])) ?></small></td><td><?= badge($c['status']) ?></td><td><small><?= e(format_date($c['created_at'])) ?></small></td></tr>
  <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?></tbody>
</table></div><div class="card-body border-top"><?= admin_pager($pg) ?></div></div>
<?php admin_footer();
