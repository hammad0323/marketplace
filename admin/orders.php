<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');

$statusFilter = clean_input($_GET['status'] ?? '');
$where = ['1=1'];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
}
$whereSql = implode(' AND ', $where);

$page = max(1, (int) ($_GET['page'] ?? 1));
$pg = paginate($conn, "SELECT COUNT(*) FROM orders o WHERE $whereSql", $params, $page, 20);

$orders = db_select(
    $conn,
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email,
        (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE $whereSql ORDER BY o.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$revenue = db_select_one($conn, 'SELECT COALESCE(SUM(total_amount),0) AS total, COALESCE(SUM((SELECT COALESCE(SUM(commission_amount),0) FROM order_items WHERE order_id = orders.id)),0) AS commission FROM orders WHERE status NOT IN ("cancelled","refunded")');

$adminPageTitle = 'Orders';
$adminActive = 'orders';
require __DIR__ . '/_layout_top.php';
?>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-bag-check"></i></div><div class="value"><?php echo (int) $pg['total']; ?></div><div class="label">Total orders</div></div>
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-cash-stack"></i></div><div class="value"><?php echo format_price($revenue['total']); ?></div><div class="label">Order value</div></div>
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-percent"></i></div><div class="value"><?php echo format_price($revenue['commission']); ?></div><div class="label">Platform commission earned</div></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3>All orders</h3>
    <select onchange="window.location='?status='+this.value" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $s): ?>
        <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <?php if ($orders): ?>
    <table class="table-w">
      <thead><tr><th>Ref</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Placed</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><code><?php echo e($o['order_ref']); ?></code></td>
            <td><?php echo e($o['customer_name']); ?><br><span style="color:var(--ink-mute);font-size:12px;"><?php echo e($o['customer_email']); ?></span></td>
            <td><?php echo (int) $o['item_count']; ?></td>
            <td><?php echo format_price($o['total_amount']); ?></td>
            <td><?php echo status_badge($o['status']); ?></td>
            <td><?php echo e(format_date($o['created_at'])); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($pg['total_pages'] > 1): ?>
      <div style="display:flex;gap:6px;justify-content:center;margin-top:18px;">
        <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
          <a href="?page=<?php echo $i; ?>&status=<?php echo e($statusFilter); ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-bag-check"></i></div><h4>No orders match this filter</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
