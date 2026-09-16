<?php
require_once __DIR__ . '/../includes/functions.php';
require_customer_login();
$customer = current_customer();
$id = (int)($_GET['id'] ?? 0);
$order = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM orders WHERE id = $id AND customer_id = {$customer['id']}"));
if (!$order) redirect(url('account/orders'));
$items = mysqli_query($mysqli, "SELECT * FROM order_items WHERE order_id = $id");
$history = mysqli_query($mysqli, "SELECT * FROM order_status_history WHERE order_id = $id ORDER BY created_at DESC");

$pageTitle = 'Order ' . $order['order_number'] . ' | ' . get_setting('store_name');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container section-tight">
  <div class="row g-4">
    <div class="col-lg-3"><?php include __DIR__ . '/../includes/account_sidebar.php'; ?></div>
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 font-serif mb-0">Order <?= e($order['order_number']) ?></h1>
        <a href="<?= url('invoice/' . $id) ?>" target="_blank" class="btn-outline-brand">View Invoice</a>
      </div>
      <div class="summary-box mb-4">
        <span class="badge status-badge status-<?= e($order['order_status']) ?> mb-3"><?= e(ucwords(str_replace('_',' ',$order['order_status']))) ?></span>
        <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
          <?php while ($it = mysqli_fetch_assoc($items)): ?>
            <tr>
              <td><?= e($it['product_name']) ?><?= $it['variation_label']?' ('.e($it['variation_label']).')':'' ?></td>
              <td><?= format_price($it['price']) ?></td>
              <td><?= (int)$it['qty'] ?></td>
              <td><?= format_price($it['subtotal']) ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
        </div>
        <table class="table w-auto ms-auto">
          <tr><td class="text-muted">Subtotal</td><td><?= format_price($order['subtotal']) ?></td></tr>
          <tr><td class="text-muted">Discount</td><td>- <?= format_price($order['discount']) ?></td></tr>
          <tr><td class="text-muted">Shipping</td><td><?= format_price($order['shipping_fee']) ?></td></tr>
          <tr><td class="text-muted">Tax</td><td><?= format_price($order['tax']) ?></td></tr>
          <tr class="fw-bold"><td>Total</td><td><?= format_price($order['total']) ?></td></tr>
        </table>
      </div>
      <div class="summary-box">
        <h2 class="h6 mb-3">Order Timeline</h2>
        <ul class="list-unstyled small">
          <?php while ($h = mysqli_fetch_assoc($history)): ?>
            <li class="mb-2"><span class="badge status-badge status-<?= e($h['status']) ?>"><?= e(ucwords(str_replace('_',' ',$h['status']))) ?></span> <span class="text-muted"><?= e(date('d M Y H:i', strtotime($h['created_at']))) ?></span></li>
          <?php endwhile; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
