<?php
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$order = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM orders WHERE id = $id"));
if (!$order) { http_response_code(404); die('Order not found.'); }

$allowed = admin_logged_in()
    || (customer_logged_in() && (int)$order['customer_id'] === (int)$_SESSION['customer_id'])
    || (!empty($_SESSION['last_order_id']) && (int)$_SESSION['last_order_id'] === $id);

if (!$allowed) { http_response_code(403); die('You are not authorized to view this invoice.'); }

$items = mysqli_query($mysqli, "SELECT * FROM order_items WHERE order_id = $id");
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($order['order_number']) ?></title>
<style>
body{font-family:Arial,sans-serif;color:#222;max-width:800px;margin:2rem auto;padding:0 1rem;}
.inv-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #a5763f;padding-bottom:1rem;margin-bottom:1.5rem;}
.brand{font-family:Georgia,serif;font-size:1.6rem;font-weight:700;color:#1c1a17;}
table{width:100%;border-collapse:collapse;margin-bottom:1.5rem;}
th,td{padding:.5rem;text-align:left;border-bottom:1px solid #eee;}
.totals td{border:none;padding:.25rem .5rem;}
.totals{width:280px;margin-left:auto;}
.badge{display:inline-block;padding:.2rem .6rem;border-radius:4px;background:#f2e9dc;color:#6b4a1e;font-size:.8rem;}
@media print{ .no-print{display:none;} }
</style>
</head>
<body>
<div class="inv-head">
  <div>
    <div class="brand"><?= e(get_setting('store_name', 'Fashion Store')) ?></div>
    <div><?= e(get_setting('store_address')) ?></div>
    <div><?= e(get_setting('store_email')) ?> | <?= e(get_setting('store_phone')) ?></div>
  </div>
  <div class="text-end" style="text-align:right">
    <h2>Invoice</h2>
    <div>Order #: <strong><?= e($order['order_number']) ?></strong></div>
    <div>Date: <?= e(date('d M Y', strtotime($order['created_at']))) ?></div>
    <div class="badge"><?= e(ucwords(str_replace('_',' ',$order['order_status']))) ?></div>
  </div>
</div>

<div style="display:flex;justify-content:space-between;margin-bottom:1.5rem;">
  <div>
    <strong>Bill To</strong><br>
    <?= e($order['guest_name']) ?><br>
    <?= e($order['guest_email']) ?><br>
    <?= e($order['guest_phone']) ?>
  </div>
  <div style="text-align:right">
    <strong>Ship To</strong><br>
    <?= e($order['shipping_address']) ?><br>
    <?= e($order['shipping_city']) ?>, <?= e($order['shipping_state']) ?> <?= e($order['shipping_postal_code']) ?><br>
    <?= e($order['shipping_country']) ?>
  </div>
</div>

<table>
  <thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
  <tbody>
  <?php while ($it = mysqli_fetch_assoc($items)): ?>
    <tr>
      <td><?= e($it['product_name']) ?><?= $it['variation_label'] ? ' ('.e($it['variation_label']).')' : '' ?></td>
      <td><?= e($it['sku']) ?></td>
      <td><?= format_price($it['price']) ?></td>
      <td><?= (int)$it['qty'] ?></td>
      <td><?= format_price($it['subtotal']) ?></td>
    </tr>
  <?php endwhile; ?>
  </tbody>
</table>

<table class="totals">
  <tr><td>Subtotal</td><td><?= format_price($order['subtotal']) ?></td></tr>
  <tr><td>Discount</td><td>- <?= format_price($order['discount']) ?></td></tr>
  <tr><td>Shipping</td><td><?= format_price($order['shipping_fee']) ?></td></tr>
  <tr><td>Tax</td><td><?= format_price($order['tax']) ?></td></tr>
  <tr style="font-weight:bold;font-size:1.1rem;border-top:2px solid #222;"><td>Total</td><td><?= format_price($order['total']) ?></td></tr>
</table>

<p>Payment Method: <strong><?= e(strtoupper($order['payment_method'])) ?></strong> — Status: <strong><?= e(ucfirst($order['payment_status'])) ?></strong></p>

<button class="no-print" onclick="window.print()">Print</button>
</body>
</html>
