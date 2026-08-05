<?php
require __DIR__ . '/../config/config.php';

$customer = mp_require_customer();
$orders = mp_orders_for_customer($customer['id']);

$pageTitle = 'My Orders';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<h1 class="reveal">My Orders</h1>

<?php if (!$orders): ?>
    <div class="empty-state reveal">
        <span class="empty-state-icon">📦</span>
        <h2>No orders yet</h2>
        <p>Everything you order will show up here.</p>
        <a class="btn" href="<?= mp_e(ROUTE_HOME) ?>">Start Shopping</a>
    </div>
<?php else: ?>
    <div class="content-panel reveal">
        <table class="admin-table">
            <thead><tr><th>Order</th><th>Placed</th><th>Total</th><th>Status</th><th>Payment</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= mp_e($order['order_number']) ?></td>
                    <td><?= mp_e(date('M j, Y', strtotime($order['placed_at']))) ?></td>
                    <td>$<?= number_format((float) $order['total_amount'], 2) ?></td>
                    <td><span class="status-chip status-<?= mp_e($order['status']) ?>"><?= mp_e(ucfirst($order['status'])) ?></span></td>
                    <td><span class="status-chip status-payment-<?= mp_e($order['payment_status']) ?>"><?= mp_e(ucfirst($order['payment_status'])) ?></span></td>
                    <td><a href="<?= mp_e(ROUTE_ORDERS) ?>details.php?number=<?= mp_e($order['order_number']) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
