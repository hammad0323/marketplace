<?php
require __DIR__ . '/includes/bootstrap.php';

$t = cart_totals();
$seo = ['title' => 'Shopping Bag | ' . setting('site_name'), 'noindex' => true];
require ROOT . '/includes/header.php';
?>
<section class="page-title">
  <div class="container"><span class="ornament">✦</span><h1 class="section-title">Shopping Bag</h1></div>
</section>
<section class="section section--tight">
  <div class="container">
    <?php if (!$t['items']): ?>
      <div class="empty-state" data-reveal>
        <?= icon('bag', 52) ?>
        <h3>Your bag is empty</h3>
        <p>Discover something beautiful from our latest collections.</p>
        <a class="btn btn-dark" href="<?= url('shop') ?>">Continue Shopping</a>
      </div>
    <?php else: ?>
    <div class="cart-layout">
      <form method="post" action="<?= url('cart-action') ?>" class="cart-table">
        <?= csrf_field() ?><input type="hidden" name="action" value="update">
        <div class="cart-row cart-row--head"><span>Product</span><span>Price</span><span>Quantity</span><span>Total</span></div>
        <?php foreach ($t['items'] as $it): ?>
          <div class="cart-row" data-reveal>
            <div class="cart-prod">
              <a href="<?= product_url($it['product']) ?>"><img src="<?= e(img($it['product']['image'])) ?>" alt=""></a>
              <div>
                <a href="<?= product_url($it['product']) ?>" class="cart-prod__name"><?= e($it['product']['name']) ?></a>
                <?php if ($it['size']): ?><small>Size: <?= e($it['size']) ?></small><?php endif; ?>
                <?php if ($it['color']): ?><small>Colour: <?= e($it['color']) ?></small><?php endif; ?>
                <button class="link-underline sm" name="remove" value="<?= e($it['key']) ?>">Remove</button>
              </div>
            </div>
            <span data-label="Price"><?= money($it['price']) ?></span>
            <span data-label="Qty"><div class="qty"><button type="button" data-qty="-1"><?= icon('minus', 14) ?></button><input type="number" name="qty[<?= e($it['key']) ?>]" value="<?= $it['qty'] ?>" min="0" max="<?= (int)$it['product']['stock'] ?>"><button type="button" data-qty="1"><?= icon('plus', 14) ?></button></div></span>
            <strong data-label="Total"><?= money($it['total']) ?></strong>
          </div>
        <?php endforeach; ?>
        <div class="cart-actions">
          <a class="link-underline" href="<?= url('shop') ?>">← Continue shopping</a>
          <button class="btn btn-outline btn-sm" type="submit">Update Bag</button>
        </div>
      </form>

      <aside class="summary" data-reveal data-delay="1">
        <h3>Order Summary</h3>
        <div class="summary__row"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div>
        <?php if ($t['discount'] > 0): ?>
          <div class="summary__row text-gold"><span>Discount (<?= e($t['coupon']) ?>)</span><span>− <?= money($t['discount']) ?></span></div>
        <?php endif; ?>
        <div class="summary__row"><span>Delivery</span><span><?= $t['shipping'] > 0 ? money($t['shipping']) : 'Free' ?></span></div>
        <small class="muted">Delivery is calculated by city at checkout.</small>
        <div class="summary__row summary__total"><span>Total</span><span><?= money($t['total']) ?></span></div>
        <form method="post" action="<?= url('cart-action') ?>" class="coupon-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="coupon">
          <?php if ($t['coupon']): ?>
            <input type="hidden" name="remove" value="1"><span class="coupon-on">✦ <?= e($t['coupon']) ?> applied</span><button class="link-underline sm" type="submit">Remove</button>
          <?php else: ?>
            <input type="text" name="code" placeholder="Coupon code"><button class="btn btn-outline btn-sm" type="submit">Apply</button>
          <?php endif; ?>
        </form>
        <a class="btn btn-gold btn-block" href="<?= url('checkout') ?>">Proceed to Checkout</a>
        <div class="secure-note"><?= icon('shield', 16) ?> Secure checkout · COD available</div>
      </aside>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
