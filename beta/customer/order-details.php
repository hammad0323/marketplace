<?php
require __DIR__ . '/../config/config.php';
require_customer();
$customer = current_customer();

$id = (int)($_GET['id'] ?? 0);
$order = db_fetch_one("SELECT o.*, a.full_name, a.address_line, a.city, a.country, a.phone as address_phone
    FROM orders o LEFT JOIN addresses a ON a.id=o.address_id WHERE o.id=? AND o.customer_id=?", 'ii', [$id, $customer['id']]);
if (!$order) { flash('error', 'Order not found.'); redirect(customer_url('orders.php')); }

$shopOrders = db_fetch_all("SELECT so.*, s.shop_name, s.slug FROM shop_orders so JOIN shops s ON s.id=so.shop_id WHERE so.order_id=?", 'i', [$id]);
foreach ($shopOrders as &$so) {
    $so['items'] = db_fetch_all("SELECT * FROM order_items WHERE shop_order_id=?", 'i', [$so['id']]);
    $so['history'] = db_fetch_all("SELECT * FROM order_status_history WHERE shop_order_id=? ORDER BY created_at", 'i', [$so['id']]);
}
unset($so);

$timelineSteps = ['placed' => 'Order Placed', 'payment_confirmed' => 'Payment Confirmed', 'confirmed' => 'Order Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];

$dashRole = 'customer'; $pageTitle = 'Order ' . $order['order_number']; $dashUserName = $customer['first_name']; $dashLogoutUrl = base_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Order <?= clean($order['order_number']) ?></h1><a href="<?= customer_url('orders.php') ?>" class="btn btn-outline">&larr; Back</a></div>
<div class="dash-table-card">
  <p><strong>Delivery Address:</strong> <?= clean($order['full_name']) ?>, <?= clean($order['address_line']) ?>, <?= clean($order['city']) ?>, <?= clean($order['country']) ?> — <?= clean($order['address_phone']) ?></p>
  <p><strong>Payment Method:</strong> <?= clean(str_replace('_',' ',$order['payment_method'])) ?> &nbsp; <strong>Payment Status:</strong> <?= clean($order['payment_status']) ?></p>
</div>

<?php foreach ($shopOrders as $so): ?>
  <div class="dash-table-card">
    <div class="dash-header-row">
      <h3><i class="fa-solid fa-store"></i> <?= clean($so['shop_name']) ?> — <?= clean($so['shop_order_number']) ?></h3>
      <?php if (in_array($so['status'], ['placed','confirmed'], true)): ?>
        <button class="btn btn-sm btn-danger cancel-order-btn" data-shop-order-id="<?= $so['id'] ?>">Cancel Order</button>
      <?php endif; ?>
    </div>
    <div class="order-status-timeline">
      <?php $order_reached = array_search($so['status'], array_keys($timelineSteps));
      $i = 0; foreach ($timelineSteps as $key => $label): ?>
        <div class="timeline-step <?= $i <= $order_reached ? 'done' : '' ?>"><span class="dot"></span><?= $label ?></div>
      <?php $i++; endforeach; ?>
      <?php if ($so['status'] === 'cancelled'): ?><div class="timeline-step done cancelled"><span class="dot"></span>Cancelled</div><?php endif; ?>
    </div>
    <table class="dash-table">
      <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($so['items'] as $it): ?>
          <tr><td><?= clean($it['product_name']) ?></td><td><?= $it['quantity'] ?></td><td><?= format_price($it['unit_price']) ?></td><td><?= format_price($it['line_total']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endforeach; ?>

<div class="dash-table-card">
  <h3>Order Total: <?= format_price($order['grand_total']) ?></h3>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
