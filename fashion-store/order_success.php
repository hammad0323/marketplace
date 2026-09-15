<?php
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
$order = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM orders WHERE id = $id"));
if (!$order || empty($_SESSION['last_order_id']) || (int)$_SESSION['last_order_id'] !== $id) {
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Order Confirmed | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section text-center">
  <i class="bi bi-check-circle display-3 text-success"></i>
  <h1 class="h3 font-serif mt-3 mb-2">Thank you for your order!</h1>
  <p class="text-muted">Your order <strong><?= e($order['order_number']) ?></strong> has been placed successfully.</p>
  <p class="text-muted">We've recorded your payment method as <strong><?= e(strtoupper($order['payment_method'])) ?></strong>. You will receive updates via email as your order progresses.</p>
  <div class="d-flex gap-3 justify-content-center mt-4">
    <a href="<?= BASE_URL ?>/invoice.php?id=<?= $id ?>" target="_blank" class="btn-outline-brand">View Invoice</a>
    <a href="<?= BASE_URL ?>/shop.php" class="btn-brand">Continue Shopping</a>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
