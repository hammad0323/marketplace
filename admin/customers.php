<?php
require __DIR__ . '/includes/init.php';
admin_require('customers');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'toggle') db_exec('UPDATE customers SET is_active = 1 - is_active WHERE id = ?', [$id]);
    flash('success', 'Customer updated.');
    redirect($_SERVER['HTTP_REFERER'] ?? 'admin/customers.php');
}
$q = input('q'); $type = input('type');
$where = ['c.deleted_at IS NULL', "c.phone <> '00000000000'"]; $params = [];
if ($q !== '') { $like = '%' . db_like($q) . '%'; $where[] = '(c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)'; array_push($params, $like, $like, $like); }
if ($type === 'registered') $where[] = 'c.is_registered = 1';
if ($type === 'guest') $where[] = 'c.is_registered = 0';
$w = implode(' AND ', $where);
$agg = "(SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id AND o.status NOT IN ('draft','cancelled')) AS orders,
    (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.status NOT IN ('draft','cancelled')) AS spent,
    (SELECT MAX(created_at) FROM orders o WHERE o.customer_id = c.id) AS last_order";
if (input('export') === '1') {
    $rows = db_all("SELECT c.name, c.phone, c.email, c.is_registered, c.source, c.created_at, $agg FROM customers c WHERE $w ORDER BY c.id DESC", $params);
    csv_download('customers.csv', ['Name', 'Phone', 'Email', 'Registered', 'Source', 'Joined', 'Orders', 'Spent', 'Last order'], array_map('array_values', $rows));
}
$pg = paginate((int) db_val("SELECT COUNT(*) FROM customers c WHERE $w", $params), 30);
$rows = db_all("SELECT c.*, $agg FROM customers c WHERE $w ORDER BY c.id DESC LIMIT 30 OFFSET {$pg['offset']}", $params);
admin_header('Customers', 'customers');
?>
<form class="a-filter"><input type="search" name="q" class="form-control form-control-sm" placeholder="Name, phone, email" value="<?= e($q) ?>">
  <?= a_sel('type', ['registered' => 'Registered', 'guest' => 'Guest / POS'], $type, 'All customers') ?><button class="btn btn-sm btn-light">Search</button>
  <a href="<?= e(url_with(['export' => 1])) ?>" class="btn btn-sm btn-outline-secondary">Export CSV</a><span class="ms-auto small text-muted"><?= $pg['total'] ?> customers</span></form>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Customer</th><th>Contact</th><th>Type</th><th>Orders</th><th class="text-end">Spent</th><th>Last order</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $c): ?>
  <tr class="<?= $c['is_active'] ? '' : 'opacity-50' ?>"><td><a href="<?= aurl('customer-view.php?id=' . $c['id']) ?>" class="fw-semibold"><?= e($c['name']) ?></a><br><small class="text-muted">Since <?= fmt_date($c['created_at']) ?></small></td>
    <td class="small"><a href="tel:<?= e($c['phone']) ?>"><?= e($c['phone']) ?></a> <a href="<?= e(whatsapp_link($c['phone'])) ?>" class="text-success" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i></a><br><?= e($c['email']) ?></td>
    <td><?= $c['is_registered'] ? '<span class="badge bg-success">Registered</span>' : '<span class="badge bg-light text-dark border">' . e(ucfirst($c['source'])) . '</span>' ?></td>
    <td><?= (int) $c['orders'] ?></td><td class="text-end"><?= money($c['spent']) ?></td><td class="small"><?= $c['last_order'] ? time_ago($c['last_order']) : '—' ?></td>
    <td class="text-end"><a href="<?= aurl('customer-view.php?id=' . $c['id']) ?>" class="btn btn-sm btn-outline-secondary">View</a></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="text-center text-muted p-4">No customers found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="mt-3"><?= admin_pagination($pg) ?></div>
<?php admin_footer();
