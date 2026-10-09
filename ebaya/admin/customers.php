<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('customers.manage');
$q = mb_substr(get('q'), 0, 100);
$w = '1=1';
$p = [];
if ($q !== '') { $w = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)'; $p = ["%$q%", "%$q%", "%$q%"]; }
if (get('export') === 'csv' && can('reports.export')) {
    audit('customers_export', 'customer');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ebaya-customers-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Phone', 'Status', 'Marketing opt-in', 'Orders', 'Spent (paid)', 'Joined']);
    foreach (db_all("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) n, (SELECT COALESCE(SUM(grand_total-refunded_total),0) FROM orders o WHERE o.customer_id = c.id AND o.payment_status IN ('paid','partially_refunded')) spent FROM customers c WHERE $w ORDER BY c.id DESC", $p) as $c) {
        fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, [$c['name'], $c['email'], $c['phone'], $c['status'], $c['marketing_opt_in'] ? 'yes' : 'no', $c['n'], $c['spent'], $c['created_at']]));
    }
    exit;
}
$pg = paginate((int)db_val("SELECT COUNT(*) FROM customers c WHERE $w", $p), 30, (int)get('page', 1));
$rows = db_all("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) n, (SELECT COALESCE(SUM(grand_total-refunded_total),0) FROM orders o WHERE o.customer_id = c.id AND o.payment_status IN ('paid','partially_refunded')) spent FROM customers c WHERE $w ORDER BY c.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $p);
$guests = (int)db_val('SELECT COUNT(DISTINCT email) FROM orders WHERE customer_id IS NULL');
$admin_title = 'Customers';
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex gap-2 mb-3">
  <form class="d-flex gap-2"><input class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Name, email or phone" style="width:260px"><button class="btn btn-sm btn-outline-secondary">Search</button></form>
  <span class="small text-muted align-self-center ms-2"><?= $guests ?> guest buyers (see Orders)</span>
  <?php if (can('reports.export')): ?><a class="btn btn-sm btn-outline-secondary ms-auto" href="<?= e(query_with(['export' => 'csv'])) ?>"><i class="bi bi-download"></i> Export CSV</a><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
  <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th class="text-end">Spent</th><th>Status</th><th>Joined</th></tr></thead>
  <tbody><?php foreach ($rows as $c): ?>
    <tr><td><a href="<?= e(admin_url('customer-view?id=' . $c['id'])) ?>"><?= e($c['name']) ?></a></td><td><?= e($c['email']) ?></td><td><?= e($c['phone']) ?></td><td><?= (int)$c['n'] ?></td><td class="text-end"><?= money($c['spent']) ?></td><td><?= status_badge($c['status']) ?></td><td class="small"><?= e(substr($c['created_at'], 0, 10)) ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-muted text-center py-4">No customers yet.</td></tr><?php endif; ?></tbody>
</table></div></div>
<div class="mt-3"><?= admin_pager($pg) ?></div>
<?php require __DIR__ . '/partials/footer.php';
