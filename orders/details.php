<?php
require __DIR__ . '/../config/config.php';

$order = isset($_GET['number'])
    ? mp_find_order_by_number($_GET['number'])
    : mp_find_order((int) ($_GET['id'] ?? 0));

if (!$order) {
    require __DIR__ . '/../404.php';
    return;
}

$admin = mp_current_admin();
$customer = mp_current_customer();
$vendor = mp_current_vendor();

$isOwnerCustomer = $customer && (int) $customer['id'] === (int) $order['customer_id'];
$allItems = mp_order_items($order['id']);
$vendorItems = $vendor ? array_values(array_filter($allItems, fn ($i) => (int) $i['vendor_id'] === (int) $vendor['id'])) : [];
$isVendorParty = $vendor && $vendorItems;

if (!$admin && !$isOwnerCustomer && !$isVendorParty) {
    require __DIR__ . '/../404.php';
    return;
}

$items = ($admin || $isOwnerCustomer) ? $allItems : $vendorItems;
$history = mp_order_status_history($order['id']);
$customerRecord = $admin || $isVendorParty ? mp_find_customer($order['customer_id']) : $customer;

$pageTitle = 'Order ' . $order['order_number'];
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<div class="order-detail reveal">
    <div class="order-detail-header">
        <div>
            <h1>Order <?= mp_e($order['order_number']) ?></h1>
            <p class="order-detail-meta">Placed <?= mp_e(date('M j, Y \a\t g:i A', strtotime($order['placed_at']))) ?><?php if ($customerRecord && ($admin || $isVendorParty)): ?> by <?= mp_e($customerRecord['name']) ?><?php endif; ?></p>
        </div>
        <div class="order-status-badges">
            <span class="status-chip status-<?= mp_e($order['status']) ?>"><?= mp_e(ucfirst($order['status'])) ?></span>
            <span class="status-chip status-payment-<?= mp_e($order['payment_status']) ?>"><?= mp_e(ucfirst($order['payment_status'])) ?></span>
        </div>
    </div>

    <div class="order-detail-grid">
        <div class="content-panel">
            <h2>Items</h2>
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Vendor</th><th>Price</th><th>Qty</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= mp_e($item['product_title']) ?></td>
                        <td><?= mp_e($item['store_name']) ?></td>
                        <td>$<?= number_format((float) $item['unit_price'], 2) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td>$<?= number_format((float) $item['line_total'], 2) ?></td>
                        <td><span class="status-chip status-<?= mp_e($item['status']) ?>"><?= mp_e(ucfirst($item['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($history): ?>
            <h3 style="margin-top:1.5rem;">Order Timeline</h3>
            <ul class="order-timeline">
                <?php foreach ($history as $entry): ?>
                    <li>
                        <span class="status-chip status-<?= mp_e($entry['new_status']) ?>"><?= mp_e(ucfirst($entry['new_status'])) ?></span>
                        <span class="order-timeline-note"><?= mp_e($entry['note'] ?: 'Status updated') ?></span>
                        <time><?= mp_e(date('M j, g:i A', strtotime($entry['created_at']))) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <aside class="cart-summary">
            <h2>Summary</h2>
            <div class="cart-summary-row"><span>Subtotal</span><span>$<?= number_format((float) $order['subtotal_amount'], 2) ?></span></div>
            <div class="cart-summary-row"><span>Shipping</span><span>$<?= number_format((float) $order['shipping_amount'], 2) ?></span></div>
            <?php if ((float) $order['discount_amount'] > 0): ?>
                <div class="cart-summary-row"><span>Discount</span><span>-$<?= number_format((float) $order['discount_amount'], 2) ?></span></div>
            <?php endif; ?>
            <div class="cart-summary-row cart-summary-total"><span>Total</span><span>$<?= number_format((float) $order['total_amount'], 2) ?></span></div>
            <p style="margin-top:1rem; font-size:.85rem; color:var(--ink-500);">Payment: <?= mp_e(strtoupper($order['payment_method'])) ?></p>

            <h3 style="margin-top:1.5rem;">Shipping To</h3>
            <p style="font-size:.9rem; line-height:1.5;">
                <?= mp_e($order['shipping_name']) ?><br>
                <?= mp_e($order['shipping_line1']) ?><?= $order['shipping_line2'] ? ', ' . mp_e($order['shipping_line2']) : '' ?><br>
                <?= mp_e($order['shipping_city']) ?><?= $order['shipping_state'] ? ', ' . mp_e($order['shipping_state']) : '' ?> <?= mp_e($order['shipping_postal_code'] ?? '') ?><br>
                <?= mp_e($order['shipping_country']) ?><br>
                <?= mp_e($order['shipping_phone']) ?>
            </p>
        </aside>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
