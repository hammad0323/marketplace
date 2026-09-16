<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    csrf_verify();
    $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM coupons WHERE code = ? AND status = 'active'");
    mysqli_stmt_bind_param($stmt, 's', $code);
    mysqli_stmt_execute($stmt);
    $coupon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $now = date('Y-m-d');
    if (!$coupon) {
        flash_set('danger', 'Invalid coupon code.');
    } elseif (($coupon['start_date'] && $coupon['start_date'] > $now) || ($coupon['end_date'] && $coupon['end_date'] < $now)) {
        flash_set('danger', 'This coupon has expired or is not yet active.');
    } elseif ($coupon['usage_limit'] && $coupon['used_count'] >= $coupon['usage_limit']) {
        flash_set('danger', 'This coupon has reached its usage limit.');
    } else {
        $_SESSION['coupon_code'] = $code;
        flash_set('success', 'Coupon applied successfully.');
    }
    redirect(url('cart'));
}
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['coupon_code']);
    redirect(url('cart'));
}

$items = get_cart_items();
$subtotal = cart_totals($items);
$discount = 0;
$appliedCoupon = null;
if (!empty($_SESSION['coupon_code'])) {
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM coupons WHERE code = ? AND status='active'");
    mysqli_stmt_bind_param($stmt, 's', $_SESSION['coupon_code']);
    mysqli_stmt_execute($stmt);
    $appliedCoupon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($appliedCoupon && $subtotal >= $appliedCoupon['min_order']) {
        $discount = $appliedCoupon['type'] === 'percentage' ? $subtotal * ($appliedCoupon['value'] / 100) : $appliedCoupon['value'];
        if ($appliedCoupon['max_discount']) $discount = min($discount, $appliedCoupon['max_discount']);
    }
}

$pageTitle = 'Shopping Cart | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight">
  <h1 class="h3 font-serif mb-4">Shopping Cart</h1>
  <?php if (!$items): ?>
    <div class="text-center py-5">
      <i class="bi bi-bag display-3 text-muted"></i>
      <p class="text-muted mt-3">Your cart is empty.</p>
      <a href="<?= url('shop') ?>" class="btn-brand">Continue Shopping</a>
    </div>
  <?php else: ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="table-responsive">
      <table class="table cart-table align-middle" id="cartTable">
        <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr data-item-id="<?= (int)$it['id'] ?>">
            <td>
              <div class="d-flex gap-3 align-items-center">
                <img src="<?= e(BASE_URL . '/' . $it['image']) ?>" class="cart-thumb">
                <div>
                  <a href="<?= e(product_url($it['slug'])) ?>" class="fw-medium"><?= e($it['name']) ?></a>
                </div>
              </div>
            </td>
            <td class="item-price"><?= format_price($it['unit_price']) ?></td>
            <td style="width:130px">
              <div class="qty-box">
                <button type="button" data-dir="dec"><i class="bi bi-dash"></i></button>
                <input type="number" value="<?= (int)$it['qty'] ?>" min="1" class="cart-qty-input">
                <button type="button" data-dir="inc"><i class="bi bi-plus"></i></button>
              </div>
            </td>
            <td class="item-subtotal"><?= format_price($it['line_total']) ?></td>
            <td><button class="btn btn-sm text-danger remove-item" title="Remove"><i class="bi bi-trash"></i></button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="summary-box">
        <h2 class="h6 mb-3">Order Summary</h2>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span id="cartSubtotal"><?= format_price($subtotal) ?></span></div>
        <?php if ($appliedCoupon): ?>
        <div class="d-flex justify-content-between mb-2 text-success"><span>Coupon (<?= e($appliedCoupon['code']) ?>) <a href="?remove_coupon=1" class="text-danger small">[remove]</a></span><span>- <?= format_price($discount) ?></span></div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-3 fw-bold"><span>Total</span><span><?= format_price($subtotal - $discount) ?></span></div>

        <form method="post" class="d-flex gap-2 mb-3">
          <?= csrf_field() ?><input type="hidden" name="apply_coupon" value="1">
          <input type="text" name="coupon_code" class="form-control form-control-sm" placeholder="Coupon code" value="<?= e($_SESSION['coupon_code'] ?? '') ?>">
          <button class="btn btn-outline-dark btn-sm">Apply</button>
        </form>

        <a href="<?= url('checkout') ?>" class="btn-brand w-100 text-center d-block">Proceed to Checkout</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('#cartTable .qty-box').forEach(function(box){
  var input = box.querySelector('.cart-qty-input');
  var row = box.closest('tr');
  function update(qty){
    var body = new FormData();
    body.append('item_id', row.dataset.itemId);
    body.append('qty', qty);
    fetch(BASE_URL + '/ajax/update_cart.php', { method:'POST', body: body })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          document.querySelectorAll('.cart-count').forEach(function(el){ el.textContent = data.cart_count; });
          document.getElementById('cartSubtotal').textContent = data.subtotal_formatted;
          if (qty == 0) { row.remove(); }
          location.reload();
        }
      });
  }
  box.querySelectorAll('button').forEach(function(btn){
    btn.addEventListener('click', function(){
      var val = parseInt(input.value || '1', 10);
      val = btn.dataset.dir === 'inc' ? val + 1 : Math.max(1, val - 1);
      input.value = val;
      update(val);
    });
  });
  input.addEventListener('change', function(){ update(parseInt(input.value || '1', 10)); });
});
document.querySelectorAll('.remove-item').forEach(function(btn){
  btn.addEventListener('click', function(){
    var row = btn.closest('tr');
    var body = new FormData();
    body.append('item_id', row.dataset.itemId);
    fetch(BASE_URL + '/ajax/remove_cart.php', { method:'POST', body: body }).then(function(){ location.reload(); });
  });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
