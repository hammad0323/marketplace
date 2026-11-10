<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
$lists = db_all('SELECT g.*, COUNT(i.id) AS items FROM grocery_lists g LEFT JOIN grocery_list_items i ON i.list_id = g.id WHERE g.customer_id = ? GROUP BY g.id ORDER BY COALESCE(g.updated_at, g.created_at) DESC', [$c['id']]);
$active = 'rashan-lists'; $title = 'My Rashan Lists';
require FS_ROOT . '/partials/account-start.php';
?>
<div class="d-flex justify-content-end mb-2"><a href="<?= url('monthly-rashan/') ?>" class="btn btn-warning fw-semibold"><i class="bi bi-plus-lg me-1"></i>New list</a></div>
<?php if (!$lists): ?><div class="empty-state"><i class="bi bi-basket"></i><p>You have no saved rashan lists yet.</p></div><?php endif; ?>
<div class="row g-3">
<?php foreach ($lists as $l):
    $priced = price_lines(db_all('SELECT product_id, variation_id, quantity FROM grocery_list_items WHERE list_id = ?', [$l['id']])); ?>
  <div class="col-md-6">
    <div class="box h-100">
      <h2 class="h6 fw-bold mb-1"><?= e($l['name']) ?></h2>
      <p class="small text-muted mb-2"><?= (int) $l['items'] ?> items · updated <?= fmt_date($l['updated_at'] ?: $l['created_at']) ?></p>
      <div class="summary-row small"><span>Current estimated cost</span><strong><?= money($priced['subtotal']) ?></strong></div>
      <?php if ($l['monthly_budget']): ?><div class="summary-row small"><span>Budget</span><span><?= money($l['monthly_budget']) ?></span></div>
      <div class="summary-row small <?= $l['monthly_budget'] - $priced['subtotal'] < 0 ? 'text-danger' : 'text-success' ?>"><span>Remaining</span><strong><?= money($l['monthly_budget'] - $priced['subtotal']) ?></strong></div><?php endif; ?>
      <?php if ($l['notes']): ?><p class="small mt-2 mb-0"><i class="bi bi-sticky me-1"></i><?= e($l['notes']) ?></p><?php endif; ?>
      <a href="<?= url('monthly-rashan/?list=' . (int) $l['id']) ?>" class="btn btn-outline-brand btn-sm mt-3">Open &amp; edit</a>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php require FS_ROOT . '/partials/account-end.php';
