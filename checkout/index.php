<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
$items = mp_cart_items_for_customer($customer['id']);
if (!$items) {
    mp_redirect(ROUTE_CART . 'view.php');
}

$subtotal = mp_cart_subtotal($items);
$addresses = mp_addresses_for_customer($customer['id']);

$pageTitle = 'Checkout';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<h1 class="reveal">Checkout</h1>

<form method="post" action="place.php" class="checkout-layout reveal">
    <?= mp_csrf_field() ?>

    <div class="checkout-main">
        <div class="content-panel">
            <h2>Shipping Address</h2>

            <?php if ($addresses): ?>
                <div class="address-picker">
                    <?php foreach ($addresses as $i => $address): ?>
                        <label class="address-option">
                            <input type="radio" name="address_id" value="<?= (int) $address['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            <span>
                                <strong><?= mp_e($address['full_name']) ?></strong> — <?= mp_e($address['label'] ?: 'Address') ?><br>
                                <?= mp_e($address['line1']) ?><?= $address['line2'] ? ', ' . mp_e($address['line2']) : '' ?>,
                                <?= mp_e($address['city']) ?><?= $address['state'] ? ', ' . mp_e($address['state']) : '' ?>,
                                <?= mp_e($address['country']) ?><br>
                                <?= mp_e($address['phone']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <label class="address-option">
                        <input type="radio" name="address_id" value="new">
                        <span><strong>Use a new address</strong></span>
                    </label>
                </div>
            <?php endif; ?>

            <div class="checkout-new-address<?= $addresses ? '' : ' is-open' ?>" id="new-address-fields">
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required value="<?= mp_e($customer['name']) ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="line1">Address Line 1</label>
                    <input type="text" id="line1" name="line1" required>
                </div>
                <div class="form-group">
                    <label for="line2">Address Line 2</label>
                    <input type="text" id="line2" name="line2">
                </div>
                <div class="checkout-address-grid">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="state">State / Province</label>
                        <input type="text" id="state" name="state">
                    </div>
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code">
                    </div>
                </div>
                <div class="form-group">
                    <label for="country">Country</label>
                    <input type="text" id="country" name="country" required value="Pakistan">
                </div>
            </div>
        </div>

        <div class="content-panel">
            <h2>Payment Method</h2>
            <div class="checkbox-grid" style="grid-template-columns: 1fr;">
                <label><input type="radio" name="payment_method" value="cod" checked> Cash on Delivery</label>
                <label><input type="radio" name="payment_method" value="manual"> Manual / Bank Transfer</label>
            </div>
        </div>
    </div>

    <aside class="cart-summary">
        <h2>Order Summary</h2>
        <?php foreach ($items as $item): ?>
            <div class="cart-summary-row"><span><?= mp_e($item['title']) ?> &times; <?= (int) $item['quantity'] ?></span><span><?= mp_currency((float) $item['price'] * (int) $item['quantity']) ?></span></div>
        <?php endforeach; ?>
        <div class="cart-summary-row"><span>Shipping</span><span>Free</span></div>
        <div class="cart-summary-row cart-summary-total"><span>Total</span><span><?= mp_currency($subtotal) ?></span></div>
        <button type="submit" class="btn" style="width:100%; margin-top:1rem;">Place Order</button>
    </aside>
</form>

<script>
document.querySelectorAll('input[name="address_id"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.getElementById('new-address-fields').classList.toggle('is-open', this.value === 'new');
    });
});
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
