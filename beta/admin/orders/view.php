<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$shopOrder = db_fetch_one("SELECT so.*, s.shop_name, o.order_number, o.payment_method, o.customer_id, c.first_name, c.last_name, c.email, c.phone
    FROM shop_orders so JOIN shops s ON s.id=so.shop_id JOIN orders o ON o.id=so.order_id JOIN customers c ON c.id=o.customer_id
    WHERE so.id=?", 'i', [$id]);
if (!$shopOrder) { flash('error', 'Order not found.'); redirect(admin_url('orders/index.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $status = $_POST['status'];
    db_exec("UPDATE shop_orders SET status=? WHERE id=?", 'si', [$status, $id]);
    db_insert("INSERT INTO order_status_history (shop_order_id, status, note) VALUES (?,?, 'Updated by admin')", 'is', [$id, $status]);
    notify('customer', $shopOrder['customer_id'], 'Order Status Updated', "Your order {$shopOrder['shop_order_number']} is now: $status", 'customer/orders.php');
    flash('success', 'Order status updated.');
    redirect(admin_url('orders/view.php?id=' . $id));
}

$items = db_fetch_all("SELECT * FROM order_items WHERE shop_order_id=?", 'i', [$id]);
$history = db_fetch_all("SELECT * FROM order_status_history WHERE shop_order_id=? ORDER BY created_at", 'i', [$id]);

$dashRole = 'admin'; $pageTitle = 'Order ' . $shopOrder['shop_order_number']; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Order <?= clean($shopOrder['shop_order_number']) ?> <small class="text-muted">(Parent: <?= clean($shopOrder['order_number']) ?>)</small></h1></div>
<div class="dash-two-col">
  <div class="dash-table-card">
    <h3>Customer</h3>
    <p><?= clean($shopOrder['first_name'] . ' ' . $shopOrder['last_name']) ?><br><?= clean($shopOrder['email']) ?><br><?= clean($shopOrder['phone']) ?></p>
    <p><strong>Shop:</strong> <?= clean($shopOrder['shop_name']) ?></p>
    <p><strong>Payment Method:</strong> <?= clean($shopOrder['payment_method']) ?></p>
    <form method="post" class="inline-form">
      <?= csrf_field() ?>
      <label>Order Status</label>
      <select name="status" onchange="this.form.submit()">
        <?php foreach (['placed','confirmed','processing','shipped','delivered','cancelled','refunded'] as $st): ?>
          <option value="<?= $st ?>" <?= $shopOrder['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="dash-table-card">
    <h3>Timeline</h3>
    <ul class="order-timeline">
      <?php foreach ($history as $h): ?><li><strong><?= ucfirst($h['status']) ?></strong> — <?= date('M d, Y g:ia', strtotime($h['created_at'])) ?><br><span class="text-muted"><?= clean($h['note']) ?></span></li><?php endforeach; ?>
    </ul>
  </div>
</div>
<div class="dash-table-card">
  <h3>Items</h3>
  <table class="dash-table">
    <thead><tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Line Total</th><th>Commission %</th><th>Commission</th><th>Vendor Earning</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= clean($it['product_name']) ?></td><td><?= $it['quantity'] ?></td><td><?= format_price($it['unit_price']) ?></td>
            <td><?= format_price($it['line_total']) ?></td><td><?= $it['commission_percent'] ?>%</td><td><?= format_price($it['commission_amount']) ?></td><td><?= format_price($it['vendor_amount']) ?></td></tr>
      <?php endforeach; ?>
      <tr class="totals-row"><td colspan="3"><strong>Total</strong></td><td><strong><?= format_price($shopOrder['subtotal']) ?></strong></td><td></td>
          <td><strong><?= format_price($shopOrder['commission_total']) ?></strong></td><td><strong><?= format_price($shopOrder['vendor_earning']) ?></strong></td></tr>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
