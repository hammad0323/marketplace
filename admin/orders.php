<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();

$orders = mp_all_orders();

$pageTitle = 'Orders';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Orders</h1>

<div class="admin-panel">
    <?php if (!$orders): ?>
        <p>No orders yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Order</th><th>Customer</th><th>Placed</th><th>Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= mp_e($order['order_number']) ?></td>
                    <td><?= mp_e($order['customer_name']) ?><br><small style="color:var(--ink-500);"><?= mp_e($order['customer_email']) ?></small></td>
                    <td><?= mp_e(date('M j, Y', strtotime($order['placed_at']))) ?></td>
                    <td><?= mp_currency((float) $order['total_amount']) ?></td>
                    <td><span class="status-chip status-<?= mp_e($order['status']) ?>"><?= mp_e(ucfirst($order['status'])) ?></span></td>
                    <td><span class="status-chip status-payment-<?= mp_e($order['payment_status']) ?>"><?= mp_e(ucfirst($order['payment_status'])) ?></span></td>
                    <td><a href="<?= mp_e(ROUTE_ORDERS) ?>details.php?number=<?= mp_e($order['order_number']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
