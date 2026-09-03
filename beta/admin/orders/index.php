<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE so.status = ?" : "";
$params = $statusFilter ? [$statusFilter] : []; $types = $statusFilter ? 's' : '';

$count = db_fetch_one("SELECT COUNT(*) c FROM shop_orders so $where", $types, $params);
$pagination = paginate((int)$count['c'], 20);
$orders = db_fetch_all("SELECT so.*, s.shop_name, o.order_number, o.customer_id, c.first_name, c.last_name
    FROM shop_orders so JOIN shops s ON s.id=so.shop_id JOIN orders o ON o.id=so.order_id JOIN customers c ON c.id=o.customer_id
    $where ORDER BY so.created_at DESC LIMIT ? OFFSET ?", $types . 'ii', [...$params, $pagination['perPage'], $pagination['offset']]);

$dashRole = 'admin'; $pageTitle = 'Orders'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Orders</h1></div>
<div class="filter-tabs">
  <?php foreach (['' => 'All', 'placed' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $v): ?>
    <a class="<?= $statusFilter === $k ? 'active' : '' ?>" href="<?= admin_url('orders/index.php?status=' . $k) ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Shop Order #</th><th>Parent Order</th><th>Customer</th><th>Shop</th><th>Total</th><th>Commission</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if ($orders): foreach ($orders as $o): ?>
      <tr>
        <td><?= clean($o['shop_order_number']) ?></td>
        <td><?= clean($o['order_number']) ?></td>
        <td><?= clean($o['first_name'] . ' ' . $o['last_name']) ?></td>
        <td><?= clean($o['shop_name']) ?></td>
        <td><?= format_price($o['subtotal']) ?></td>
        <td><?= format_price($o['commission_total']) ?></td>
        <td><span class="badge badge-<?= $o['status'] === 'delivered' ? 'success' : ($o['status'] === 'cancelled' ? 'danger' : 'warn') ?>"><?= clean($o['status']) ?></span></td>
        <td><a href="<?= admin_url('orders/view.php?id=' . $o['id']) ?>" class="btn btn-sm btn-outline">View</a></td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="8"><div class="empty-state"><h3>No orders found</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, admin_url('orders/index.php?status=' . $statusFilter)) ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
