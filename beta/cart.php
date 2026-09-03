<?php
require __DIR__ . '/config/config.php';
require_customer();
$customer = current_customer();
$grouped = get_cart_items_grouped($customer['id']);
$totals = cart_totals($grouped);
$pageTitle = 'Shopping Cart';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <h1 class="page-title">Shopping Cart</h1>
  <?php if (!$grouped): ?>
    <div class="empty-state">
      <i class="fa-solid fa-cart-shopping"></i>
      <h3>Your cart is empty</h3>
      <p>Looks like you haven't added anything yet.</p>
      <a class="btn btn-primary" href="<?= base_url() ?>">Continue Shopping</a>
    </div>
  <?php else: ?>
    <div class="cart-layout">
      <div class="cart-items">
        <?php foreach ($grouped as $shopId => $shop): ?>
          <div class="cart-shop-group">
            <h3><a href="<?= base_url('shop.php?slug=' . $shop['shop_slug']) ?>"><i class="fa-solid fa-store"></i> <?= clean($shop['shop_name']) ?></a>
              <?php if ($shop['shop_status'] !== 'active'): ?><span class="badge badge-warn">Unavailable</span><?php endif; ?>
            </h3>
            <?php foreach ($shop['items'] as $item): ?>
              <div class="cart-item" data-item-id="<?= $item['id'] ?>">
                <img src="<?= product_image_or_default($item['main_image']) ?>" alt="">
                <div class="cart-item-info">
                  <a href="<?= base_url('product.php?slug=' . $item['slug']) ?>"><?= clean($item['name']) ?></a>
                  <?php if ($item['variation_label']): ?><span class="text-muted"><?= clean($item['variation_label']) ?></span><?php endif; ?>
                  <span class="price-now"><?= format_price($item['unit_price']) ?></span>
                </div>
                <div class="qty-input">
                  <button type="button" class="cart-qty-btn" data-action="dec">-</button>
                  <input type="number" class="cart-qty-value" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock_quantity'] ?>" data-item-id="<?= $item['id'] ?>">
                  <button type="button" class="cart-qty-btn" data-action="inc">+</button>
                </div>
                <span class="cart-line-total"><?= format_price($item['line_total']) ?></span>
                <button class="cart-remove-btn" data-item-id="<?= $item['id'] ?>"><i class="fa-solid fa-trash"></i></button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="cart-summary">
        <h3>Order Summary</h3>
        <div class="cart-summary-row"><span>Subtotal</span><span id="cart-subtotal"><?= format_price($totals['subtotal']) ?></span></div>
        <div class="cart-summary-row"><span>Shipping</span><span>Calculated at checkout</span></div>
        <a class="btn btn-primary btn-block" href="<?= base_url('checkout.php') ?>">Proceed to Checkout</a>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
