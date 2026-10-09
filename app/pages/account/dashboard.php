<?php
$customer = require_customer();
meta_set(['title' => 'My account', 'noindex' => true]);
$orders = db_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5', [(int) $customer['id']]);
$stats = db_one("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','refunded') THEN grand_total ELSE 0 END), 0) spent FROM orders WHERE customer_id = ?", [(int) $customer['id']]);
$address = db_one('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC LIMIT 1', [(int) $customer['id']]);
partial('header');
?>
<div class="container container--wide page-pad account">
  <h1 class="page-title">Hello, <?= e($customer['first_name']) ?></h1>
  <div class="account__layout">
    <?php require __DIR__ . '/_nav.php'; ?>
    <div class="account__main">
      <div class="stat-row">
        <div class="info-card"><h3>Orders</h3><p class="stat"><?= (int) $stats['n'] ?></p></div>
        <div class="info-card"><h3>Wishlist</h3><p class="stat"><?= wishlist_count() ?></p></div>
        <div class="info-card"><h3>Default address</h3><p><?= $address ? e($address['address_line1'] . ', ' . $address['city']) : '<a href="' . e(path_url('account/addresses')) . '">Add an address</a>' ?></p></div>
      </div>
      <h2 class="h5 mt-4">Recent orders</h2>
      <?php if ($orders): require __DIR__ . '/_orders-table.php'; else: ?>
        <p class="text-muted">No orders yet. <a href="<?= e(path_url('shop')) ?>">Start shopping</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php partial('footer');
