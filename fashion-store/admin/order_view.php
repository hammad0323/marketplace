<?php
$pageTitle = 'Order Details';
require_once __DIR__ . '/includes/admin_header.php';

$id = (int)($_GET['id'] ?? 0);
$order = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT o.*, c.name AS customer_name, c.email AS customer_email FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = $id"));
if (!$order) redirect('orders.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $newStatus = $_POST['order_status'];
        $paymentStatus = $_POST['payment_status'];
        $note = trim($_POST['note'] ?? '');
        $adminNote = trim($_POST['admin_note'] ?? '');
        $stmt = mysqli_prepare($mysqli, "UPDATE orders SET order_status=?, payment_status=?, admin_note=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssi', $newStatus, $paymentStatus, $adminNote, $id);
        mysqli_stmt_execute($stmt);
        $stmt2 = mysqli_prepare($mysqli, "INSERT INTO order_status_history (order_id, status, note) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt2, 'iss', $id, $newStatus, $note);
        mysqli_stmt_execute($stmt2);
        flash_set('success', 'Order updated.');
        redirect('order_view.php?id=' . $id);
    }
}

$items = mysqli_query($mysqli, "SELECT * FROM order_items WHERE order_id = $id");
$history = mysqli_query($mysqli, "SELECT * FROM order_status_history WHERE order_id = $id ORDER BY created_at DESC");
$statuses = ['pending','confirmed','processing','packed','shipped','out_for_delivery','delivered','cancelled','returned','refunded'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="page-title mb-0">Order <?= e($order['order_number']) ?></h1>
  <a href="../invoice.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i> Print Invoice</a>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Items</h2>
      <table class="table align-middle">
        <thead><tr><th>Product</th><th>SKU</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
        <tbody>
        <?php while ($it = mysqli_fetch_assoc($items)): ?>
          <tr>
            <td><?= e($it['product_name']) ?><?php if ($it['variation_label']): ?><br><span class="small text-muted"><?= e($it['variation_label']) ?></span><?php endif; ?></td>
            <td><?= e($it['sku']) ?></td>
            <td><?= format_price($it['price']) ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td><?= format_price($it['subtotal']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      <table class="table w-auto ms-auto">
        <tr><td class="text-muted">Subtotal</td><td><?= format_price($order['subtotal']) ?></td></tr>
        <tr><td class="text-muted">Discount<?= $order['coupon_code'] ? ' ('.e($order['coupon_code']).')' : '' ?></td><td>- <?= format_price($order['discount']) ?></td></tr>
        <tr><td class="text-muted">Shipping</td><td><?= format_price($order['shipping_fee']) ?></td></tr>
        <tr><td class="text-muted">Tax</td><td><?= format_price($order['tax']) ?></td></tr>
        <tr class="fw-bold"><td>Total</td><td><?= format_price($order['total']) ?></td></tr>
      </table>
    </div>

    <div class="admin-card">
      <h2 class="h6 mb-3">Update Status</h2>
      <form method="post" class="row g-3">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_status">
        <div class="col-md-6">
          <label class="form-label">Order Status</label>
          <select name="order_status" class="form-select">
            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $order['order_status']===$s?'selected':'' ?>><?= e(ucwords(str_replace('_',' ',$s))) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Payment Status</label>
          <select name="payment_status" class="form-select">
            <?php foreach (['pending','paid','failed','refunded'] as $s): ?><option value="<?= $s ?>" <?= $order['payment_status']===$s?'selected':'' ?>><?= e(ucfirst($s)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-12"><label class="form-label">Status Note (visible in history)</label><input type="text" name="note" class="form-control"></div>
        <div class="col-12"><label class="form-label">Internal Admin Note</label><textarea name="admin_note" class="form-control" rows="2"><?= e($order['admin_note']) ?></textarea></div>
        <div class="col-12"><button class="btn btn-primary text-white">Update Order</button></div>
      </form>

      <hr>
      <h2 class="h6 mb-3">Status History</h2>
      <ul class="list-unstyled small">
        <?php while ($h = mysqli_fetch_assoc($history)): ?>
          <li class="mb-2"><span class="badge status-badge status-<?= e($h['status']) ?>"><?= e(ucwords(str_replace('_',' ',$h['status']))) ?></span> <?= e($h['note']) ?> <span class="text-muted">— <?= e(date('d M Y H:i', strtotime($h['created_at']))) ?></span></li>
        <?php endwhile; ?>
      </ul>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Customer</h2>
      <p class="mb-1"><?= e($order['customer_name'] ?: $order['guest_name']) ?></p>
      <p class="mb-1 small text-muted"><?= e($order['customer_email'] ?: $order['guest_email']) ?></p>
      <p class="mb-0 small text-muted"><?= e($order['guest_phone']) ?></p>
    </div>
    <div class="admin-card mb-4">
      <h2 class="h6 mb-3">Shipping Address</h2>
      <p class="mb-1"><?= e($order['shipping_address']) ?></p>
      <p class="mb-1"><?= e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', '.e($order['shipping_state']) : '' ?></p>
      <p class="mb-1"><?= e($order['shipping_postal_code']) ?></p>
      <p class="mb-0"><?= e($order['shipping_country']) ?></p>
    </div>
    <div class="admin-card">
      <h2 class="h6 mb-3">Payment</h2>
      <p class="mb-1">Method: <strong><?= e(strtoupper($order['payment_method'])) ?></strong></p>
      <p class="mb-0">Status: <span class="badge text-bg-light border"><?= e(ucfirst($order['payment_status'])) ?></span></p>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
