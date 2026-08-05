<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
$items = mp_cart_items_for_customer($customer['id']);
$subtotal = mp_cart_subtotal($items);

$pageTitle = 'Your Cart';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<h1 class="reveal">Your Cart</h1>

<?php if (!$items): ?>
    <div class="empty-state reveal">
        <span class="empty-state-icon">🛒</span>
        <h2>Your cart is empty</h2>
        <p>Browse the marketplaces and add something you love.</p>
        <a class="btn" href="<?= mp_e(ROUTE_HOME) ?>">Start Shopping</a>
    </div>
<?php else: ?>
    <div class="cart-layout reveal">
        <div class="cart-items">
            <?php foreach ($items as $item): ?>
                <?php $images = json_decode($item['images'] ?? '[]', true) ?: []; ?>
                <div class="cart-row">
                    <img src="<?= mp_e($images[0] ?? (ROUTE_ASSETS . 'images/placeholder.svg')) ?>" alt="<?= mp_e($item['title']) ?>">
                    <div class="cart-row-info">
                        <a href="<?= mp_e(ROUTE_PRODUCTS) ?>details.php?slug=<?= mp_e($item['slug']) ?>"><strong><?= mp_e($item['title']) ?></strong></a>
                        <div class="cart-row-store"><?= mp_e($item['store_name']) ?></div>
                        <div class="cart-row-price">$<?= number_format((float) $item['price'], 2) ?></div>
                    </div>
                    <form method="post" action="update.php" class="cart-row-qty">
                        <?= mp_csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                        <input type="number" name="quantity" value="<?= (int) $item['quantity'] ?>" min="1" max="<?= (int) $item['stock_quantity'] ?>" onchange="this.form.submit()">
                        <button type="submit" class="btn btn-secondary btn-sm">Update</button>
                    </form>
                    <div class="cart-row-total">$<?= number_format((float) $item['price'] * (int) $item['quantity'], 2) ?></div>
                    <form method="post" action="remove.php" class="cart-row-remove">
                        <?= mp_csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                        <button type="submit" class="link-button" aria-label="Remove">✕</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <aside class="cart-summary">
            <h2>Order Summary</h2>
            <div class="cart-summary-row"><span>Subtotal</span><span>$<?= number_format($subtotal, 2) ?></span></div>
            <div class="cart-summary-row"><span>Shipping</span><span>Calculated at checkout</span></div>
            <div class="cart-summary-row cart-summary-total"><span>Total</span><span>$<?= number_format($subtotal, 2) ?></span></div>
            <a class="btn" style="width:100%; margin-top:1rem;" href="<?= mp_e(ROUTE_CHECKOUT) ?>index.php">Proceed to Checkout</a>
        </aside>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
