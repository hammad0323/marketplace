<?php
$customer = require_customer();
$number = $GLOBALS['route']['id'] ?? null;
if ($number) {
    $order = db_one('SELECT * FROM orders WHERE order_number = ? AND customer_id = ?', [$number, (int) $customer['id']]);
    if (!$order) {
        not_found();
    }
    meta_set(['title' => 'Order ' . $order['order_number'], 'noindex' => true]);
    partial('header'); ?>
    <div class="container container--wide page-pad account">
      <h1 class="page-title">Order <?= e($order['order_number']) ?></h1>
      <div class="account__layout">
        <?php $active = 'orders'; require __DIR__ . '/_nav.php'; ?>
        <div class="account__main">
          <?php partial('order-detail', ['order' => $order]); ?>
          <p class="mt-3"><button type="button" class="btn-outline-lux" onclick="window.print()"><i class="bi bi-printer"></i> Print</button></p>
        </div>
      </div>
    </div>
    <?php partial('footer');
    return;
}
meta_set(['title' => 'My orders', 'noindex' => true]);
$page = max(1, input_int('page', 1, 'get'));
$total = (int) db_val('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [(int) $customer['id']]);
$pg = paginate($total, 10, $page);
$orders = db_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT ' . $pg['per_page'] . ' OFFSET ' . $pg['offset'], [(int) $customer['id']]);
partial('header');
?>
<div class="container container--wide page-pad account">
  <h1 class="page-title">My orders</h1>
  <div class="account__layout">
    <?php $active = 'orders'; require __DIR__ . '/_nav.php'; ?>
    <div class="account__main">
      <?php if ($orders): require __DIR__ . '/_orders-table.php'; partial('pagination', ['pg' => $pg]); else: ?>
        <p class="text-muted">You have not placed any orders yet.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php partial('footer');
