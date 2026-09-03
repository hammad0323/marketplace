<?php
require __DIR__ . '/../config/config.php';
require_permission('view_orders');
$shopId = active_shop_id();
$dashRole = current_shop_owner() ? 'shop' : 'employee';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');

$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $order = db_fetch_one("SELECT so.*, o.order_number, o.payment_method, c.first_name, c.last_name, c.email, c.phone,
        a.address_line, a.city, a.country FROM shop_orders so JOIN orders o ON o.id=so.order_id JOIN customers c ON c.id=o.customer_id
        LEFT JOIN addresses a ON a.id=o.address_id WHERE so.id=? AND so.shop_id=?", 'ii', [$id, $shopId]);
    if (!$order) { flash('error', 'Order not found.'); redirect(shop_url('orders.php')); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && staff_has_permission('manage_orders')) {
        csrf_verify();
        $status = $_POST['status'];
        db_exec("UPDATE shop_orders SET status=? WHERE id=?", 'si', [$status, $id]);
        db_insert("INSERT INTO order_status_history (shop_order_id, status, note) VALUES (?,?, 'Updated by shop')", 'is', [$id, $status]);
        $customerId = db_fetch_one("SELECT customer_id FROM orders WHERE id=?", 'i', [$order['order_id']])['customer_id'];
        notify('customer', $customerId, 'Order Status Updated', "Your order {$order['shop_order_number']} is now: $status", 'customer/orders.php');
        flash('success', 'Order status updated.');
        redirect(shop_url('orders.php?id=' . $id));
    }

    $items = db_fetch_all("SELECT * FROM order_items WHERE shop_order_id=?", 'i', [$id]);
    $history = db_fetch_all("SELECT * FROM order_status_history WHERE shop_order_id=? ORDER BY created_at", 'i', [$id]);
    $pageTitle = 'Order ' . $order['shop_order_number'];
    require __DIR__ . '/../includes/dashboard-header.php';
    ?>
    <div class="dash-header-row"><h1>Order <?= clean($order['shop_order_number']) ?></h1><a href="<?= shop_url('orders.php') ?>" class="btn btn-outline">&larr; Back</a></div>
    <div class="dash-two-col">
      <div class="dash-table-card">
        <h3>Customer</h3>
        <p><?= clean($order['first_name'] . ' ' . $order['last_name']) ?><br><?= clean($order['email']) ?><br><?= clean($order['phone']) ?></p>
        <p><?= clean($order['address_line']) ?>, <?= clean($order['city']) ?>, <?= clean($order['country']) ?></p>
        <p><strong>Payment:</strong> <?= clean($order['payment_method']) ?></p>
        <?php if (staff_has_permission('manage_orders') || staff_has_permission('confirm_orders')): ?>
          <form method="post" class="inline-form">
            <?= csrf_field() ?>
            <label>Order Status</label>
            <select name="status" onchange="this.form.submit()">
              <?php foreach (['placed','confirmed','processing','shipped','delivered','cancelled','refunded'] as $st): ?>
                <option value="<?= $st ?>" <?= $order['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        <?php endif; ?>
      </div>
      <div class="dash-table-card">
        <h3>Timeline</h3>
        <ul class="order-timeline"><?php foreach ($history as $h): ?><li><strong><?= ucfirst($h['status']) ?></strong> — <?= date('M d, Y g:ia', strtotime($h['created_at'])) ?></li><?php endforeach; ?></ul>
      </div>
    </div>
    <div class="dash-table-card">
      <h3>Items</h3>
      <table class="dash-table">
        <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th><th>Commission</th><th>Your Earning</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr><td><?= clean($it['product_name']) ?></td><td><?= $it['quantity'] ?></td><td><?= format_price($it['unit_price']) ?></td>
                <td><?= format_price($it['line_total']) ?></td><td><?= format_price($it['commission_amount']) ?></td><td><?= format_price($it['vendor_amount']) ?></td></tr>
          <?php endforeach; ?>
          <tr class="totals-row"><td colspan="3"><strong>Total</strong></td><td><strong><?= format_price($order['subtotal']) ?></strong></td>
              <td><strong><?= format_price($order['commission_total']) ?></strong></td><td><strong><?= format_price($order['vendor_earning']) ?></strong></td></tr>
        </tbody>
      </table>
    </div>
    <?php require __DIR__ . '/../includes/dashboard-footer.php'; exit;
}

$statusFilter = $_GET['status'] ?? '';
$where = "so.shop_id = ?"; $params = [$shopId]; $types = 'i';
if ($statusFilter) { $where .= " AND so.status = ?"; $params[] = $statusFilter; $types .= 's'; }

$count = db_fetch_one("SELECT COUNT(*) c FROM shop_orders so WHERE $where", $types, $params);
$pagination = paginate((int)$count['c'], 20);
$orders = db_fetch_all("SELECT so.*, o.order_number, c.first_name, c.last_name FROM shop_orders so
    JOIN orders o ON o.id=so.order_id JOIN customers c ON c.id=o.customer_id WHERE $where
    ORDER BY so.created_at DESC LIMIT ? OFFSET ?", $types . 'ii', [...$params, $pagination['perPage'], $pagination['offset']]);

$pageTitle = 'Orders';
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Orders</h1></div>
<div class="filter-tabs">
  <?php foreach (['' => 'All', 'placed' => 'Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $v): ?>
    <a class="<?= $statusFilter === $k ? 'active' : '' ?>" href="<?= shop_url('orders.php?status=' . $k) ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Your Earning</th><th>Status</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php if ($orders): foreach ($orders as $o): ?>
      <tr>
        <td><?= clean($o['shop_order_number']) ?></td>
        <td><?= clean($o['first_name'] . ' ' . $o['last_name']) ?></td>
        <td><?= format_price($o['subtotal']) ?></td>
        <td><?= format_price($o['vendor_earning']) ?></td>
        <td><span class="badge badge-<?= $o['status']==='delivered'?'success':($o['status']==='cancelled'?'danger':'warn') ?>"><?= clean($o['status']) ?></span></td>
        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
        <td><a href="<?= shop_url('orders.php?id=' . $o['id']) ?>" class="btn btn-sm btn-outline">View</a></td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="7"><div class="empty-state"><h3>No orders yet</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, shop_url('orders.php?status=' . $statusFilter)) ?>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
