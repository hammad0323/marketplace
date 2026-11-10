<?php
/** /track-order/ - order number + phone (guests) */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$num = strtoupper(input('order'));
$phone = normalize_phone(input('phone'));
$o = null;
$searched = $num !== '';
if ($searched) {
    $o = db_row('SELECT * FROM orders WHERE order_number = ? AND status <> \'draft\'', [$num]);
    $own = $o && (customer_id() && (int) $o['customer_id'] === customer_id());
    if ($o && !$own && ($phone === '' || normalize_phone($o['customer_phone']) !== $phone)) $o = null;
}
page_set(['title' => 'Track your order', 'robots' => 'noindex,follow']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container py-3" style="max-width:820px">
  <div class="page-head"><?= breadcrumbs_html([['Home', url('')], ['Track Order', null]]) ?><h1>Track your order</h1></div>
  <form method="get" class="box mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-5"><label class="form-label" for="tNum">Order number</label><input id="tNum" name="order" class="form-control" placeholder="FS261110-12345" value="<?= e($num) ?>" required></div>
      <div class="col-md-4"><label class="form-label" for="tPh">Phone number</label><input id="tPh" name="phone" class="form-control" placeholder="03XXXXXXXXX" value="<?= e(input('phone')) ?>"></div>
      <div class="col-md-3"><button class="btn btn-brand w-100">Track</button></div>
    </div>
  </form>
  <?php if ($searched && !$o): ?><div class="alert alert-warning">No order found with that number and phone. Please check and try again.</div><?php endif; ?>
  <?php if ($o): require FS_ROOT . '/partials/order-tracking.php'; endif; ?>
</div>
<?php require FS_ROOT . '/partials/footer.php';
