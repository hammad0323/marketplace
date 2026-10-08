<?php
require __DIR__ . '/includes/admin.php';

if (is_post()) {
    require_csrf();
    $cid = (int)post('id');
    if (post('do') === 'toggle') {
        q('UPDATE customers SET status = 1 - status WHERE id = ?', [$cid]);
        flash('success', 'Customer status updated.');
    } elseif (post('do') === 'delete' && admin_can('settings')) {
        q('UPDATE orders SET customer_id = NULL WHERE customer_id = ?', [$cid]);
        q('DELETE FROM wishlist WHERE customer_id = ?', [$cid]);
        q('DELETE FROM customers WHERE id = ?', [$cid]);
        flash('success', 'Customer deleted. Their orders were kept.');
        redirect('admin/customers');
    } elseif (post('do') === 'password' && strlen((string)post('password')) >= 6) {
        q('UPDATE customers SET password = ? WHERE id = ?', [password_hash((string)post('password'), PASSWORD_DEFAULT), $cid]);
        flash('success', 'Password reset.');
    }
    back('admin/customers');
}

if ($id = (int)get('id')) {
    $c = row('SELECT * FROM customers WHERE id = ?', [$id]);
    if (!$c) redirect('admin/customers');
    $orders = rows('SELECT * FROM orders WHERE customer_id = ? ORDER BY id DESC', [$id]);
    admin_header($c['name'], 'customers'); ?>
    <p><a class="link" href="<?= url('admin/customers') ?>">← All customers</a></p>
    <div class="grid-2 wide-left">
      <div class="card">
        <div class="card__head"><h3>Orders (<?= count($orders) ?>)</h3><b><?= money(array_sum(array_column(array_filter($orders, fn($o) => !in_array($o['status'], ['cancelled', 'returned'], true)), 'total'))) ?> spent</b></div>
        <div class="table-wrap"><table class="table"><thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>
          <?php foreach ($orders as $o): ?><tr><td><a href="<?= url('admin/order-view?id=' . $o['id']) ?>"><?= e($o['order_no']) ?></a></td><td><?= date('d M Y', strtotime($o['created_at'])) ?></td><td><?= money($o['total']) ?></td><td><?= status_badge($o['status']) ?></td></tr><?php endforeach; ?>
          <?php if (!$orders): ?><tr><td colspan="4" class="empty">No orders yet.</td></tr><?php endif; ?>
        </tbody></table></div>
      </div>
      <div class="card">
        <div class="card__head"><h3>Details</h3><?= $c['status'] ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge badge-cancelled">Blocked</span>' ?></div>
        <div class="kv"><span>Email</span><b><?= e($c['email']) ?></b></div>
        <div class="kv"><span>Phone</span><b><?= e($c['phone']) ?></b></div>
        <div class="kv"><span>City</span><b><?= e($c['city']) ?></b></div>
        <div class="kv"><span>Address</span><b><?= e($c['address']) ?></b></div>
        <div class="kv"><span>Joined</span><b><?= date('d M Y', strtotime($c['created_at'])) ?></b></div>
        <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="do" value="password">
          <?= f_text('password', 'Reset password', '', ['type' => 'text', 'attrs' => 'minlength="6"', 'help' => 'At least 6 characters']) ?><button class="btn btn-sm">Set password</button></form>
        <form method="post" class="btn-row mt"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn btn-sm" name="do" value="toggle"><?= $c['status'] ? 'Block customer' : 'Unblock customer' ?></button>
          <?php if (admin_can('settings')): ?><button class="btn btn-sm btn-danger" name="do" value="delete" data-confirm="Delete this customer account?">Delete</button><?php endif; ?>
        </form>
      </div>
    </div>
    <?php admin_footer();
    exit;
}

$where = '1';
$params = [];
if (get('q') !== '') { $where = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)'; $params = array_fill(0, 3, '%' . get('q') . '%'); }
$total = (int)val("SELECT COUNT(*) FROM customers WHERE $where", $params);
$pg = paginate($total, 30, (int)get('page', 1));
$list = rows("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id = c.id) orders, (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.customer_id = c.id AND o.status NOT IN ('cancelled','returned')) spent FROM customers c WHERE $where ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Name', 'Email', 'Phone', 'City', 'Address', 'Joined']);
    foreach (rows('SELECT * FROM customers ORDER BY id') as $c) fputcsv($out, [$c['name'], $c['email'], $c['phone'], $c['city'], $c['address'], $c['created_at']]);
    exit;
}
admin_header('Customers', 'customers');
?>
<div class="toolbar">
  <form class="filters" method="get"><input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Search name, email or phone"><button class="btn">Search</button></form>
  <a class="btn" href="?export=csv">Export CSV</a>
</div>
<div class="card">
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Contact</th><th>City</th><th>Orders</th><th>Spent</th><th>Joined</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($list as $c): ?>
        <tr>
          <td><a href="?id=<?= $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a></td>
          <td><?= e($c['email']) ?><small class="block muted"><?= e($c['phone']) ?></small></td>
          <td><?= e($c['city']) ?></td>
          <td><?= (int)$c['orders'] ?></td>
          <td><?= money($c['spent']) ?></td>
          <td class="muted"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
          <td><?= $c['status'] ? '<span class="badge badge-delivered">Active</span>' : '<span class="badge badge-cancelled">Blocked</span>' ?></td>
          <td class="actions"><a class="icon" href="?id=<?= $c['id'] ?>"><?= aicon('eye') ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$list): ?><tr><td colspan="8" class="empty">No customers yet. Guest orders appear under Orders.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?= page_links($pg) ?>
<?php admin_footer();
