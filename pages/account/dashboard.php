<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
$stats = db_row("SELECT COUNT(*) AS orders, COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total END),0) AS spent,
    SUM(status IN ('pending','confirmed','processing','ready_for_pickup','out_for_delivery')) AS active FROM orders WHERE customer_id = ? AND status <> 'draft'", [$c['id']]);
$recent = db_all("SELECT * FROM orders WHERE customer_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 5", [$c['id']]);
$lists = (int) db_val('SELECT COUNT(*) FROM grocery_lists WHERE customer_id = ?', [$c['id']]);
$active = 'dashboard'; $title = 'My Account';
require FS_ROOT . '/partials/account-start.php';
?>
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="stat-tile"><small class="text-muted">Orders</small><strong><?= (int) $stats['orders'] ?></strong></div></div>
  <div class="col-6 col-md-3"><div class="stat-tile"><small class="text-muted">Active</small><strong><?= (int) $stats['active'] ?></strong></div></div>
  <div class="col-6 col-md-3"><div class="stat-tile"><small class="text-muted">Total spent</small><strong class="fs-5"><?= money($stats['spent']) ?></strong></div></div>
  <div class="col-6 col-md-3"><div class="stat-tile"><small class="text-muted">Rashan lists</small><strong><?= $lists ?></strong></div></div>
</div>
<div class="box mb-3">
  <div class="d-flex justify-content-between"><h2 class="box-title">Recent orders</h2><a href="<?= url('my-account/orders/') ?>" class="small">View all</a></div>
  <?php require FS_ROOT . '/partials/account-orders-table.php'; ?>
</div>
<div class="box d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div><h2 class="box-title mb-1">Monthly Rashan</h2><p class="small text-muted mb-0">Reuse last month's list in one click.</p></div>
  <a href="<?= url('monthly-rashan/') ?>" class="btn btn-warning fw-semibold">Open Rashan Builder</a>
</div>
<?php require FS_ROOT . '/partials/account-end.php';
