<?php
require __DIR__ . '/config/config.php';
require_customer();
$customer = current_customer();

$buyNowId = isset($_GET['buy_now']) ? (int)$_GET['buy_now'] : null;

if ($buyNowId) {
    $product = db_fetch_one("SELECT p.*, s.shop_name, s.slug as shop_slug, s.status as shop_status FROM products p
        JOIN shops s ON s.id=p.shop_id WHERE p.id=? AND p.status='active'", 'i', [$buyNowId]);
    if (!$product || $product['shop_status'] !== 'active') { flash('error', 'Product unavailable.'); redirect(base_url()); }
    $unitPrice = $product['sale_price'] ?? $product['regular_price'];
    $grouped = [$product['shop_id'] => [
        'shop_name' => $product['shop_name'], 'shop_slug' => $product['shop_slug'], 'shop_status' => $product['shop_status'],
        'items' => [['id' => 0, 'product_id' => $product['id'], 'name' => $product['name'], 'main_image' => $product['main_image'],
            'category_id' => $product['category_id'], 'quantity' => 1, 'unit_price' => $unitPrice, 'line_total' => $unitPrice, 'variation_id' => null]],
    ]];
} else {
    $grouped = get_cart_items_grouped($customer['id']);
    if (!$grouped) { flash('error', 'Your cart is empty.'); redirect(base_url('cart.php')); }
}
$totals = cart_totals($grouped);

$unavailable = false;
foreach ($grouped as $shop) if ($shop['shop_status'] !== 'active') $unavailable = true;

$addresses = db_fetch_all("SELECT * FROM addresses WHERE customer_id = ? ORDER BY is_default DESC", 'i', [$customer['id']]);
$paymentMethods = db_fetch_all("SELECT * FROM payment_methods WHERE is_enabled = 1 ORDER BY sort_order");

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <h1 class="page-title">Checkout</h1>
  <?php if ($unavailable): ?>
    <div class="alert alert-error">One or more shops in your order are currently unavailable. Please remove their items before checkout.</div>
  <?php endif; ?>
  <form method="post" action="<?= base_url('actions/checkout.php') ?>" id="checkout-form">
    <?= csrf_field() ?>
    <input type="hidden" name="buy_now" value="<?= clean($buyNowId ?? '') ?>">
    <div class="checkout-layout">
      <div class="checkout-main">
        <div class="checkout-block">
          <h3>Delivery Address</h3>
          <?php if ($addresses): foreach ($addresses as $i => $a): ?>
            <label class="address-option">
              <input type="radio" name="address_id" value="<?= $a['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <strong><?= clean($a['label']) ?></strong> — <?= clean($a['full_name']) ?>, <?= clean($a['address_line']) ?>, <?= clean($a['city']) ?>, <?= clean($a['phone']) ?>
            </label>
          <?php endforeach; else: ?>
            <p class="text-muted">No saved address. <a href="<?= customer_url('addresses.php') ?>">Add one</a> or enter details below.</p>
          <?php endif; ?>
        </div>
        <div class="checkout-block">
          <h3>Payment Method</h3>
          <?php foreach ($paymentMethods as $pm): ?>
            <label class="address-option">
              <input type="radio" name="payment_method" value="<?= clean($pm['method_key']) ?>" required>
              <strong><?= clean($pm['title']) ?></strong> — <?= clean($pm['description']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php foreach ($grouped as $shopId => $shop): ?>
          <div class="checkout-block">
            <h4><i class="fa-solid fa-store"></i> <?= clean($shop['shop_name']) ?></h4>
            <?php foreach ($shop['items'] as $item): ?>
              <div class="checkout-item">
                <img src="<?= product_image_or_default($item['main_image']) ?>">
                <span><?= clean($item['name']) ?> &times; <?= $item['quantity'] ?></span>
                <span><?= format_price($item['line_total']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="cart-summary">
        <h3>Order Total</h3>
        <div class="cart-summary-row"><span>Subtotal</span><span><?= format_price($totals['subtotal']) ?></span></div>
        <div class="cart-summary-row"><span>Shipping</span><span>Free</span></div>
        <div class="cart-summary-row total"><span>Total</span><span><?= format_price($totals['subtotal']) ?></span></div>
        <button type="submit" class="btn btn-primary btn-block" <?= $unavailable ? 'disabled' : '' ?>>Place Order</button>
      </div>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
