<?php
require __DIR__ . '/../config/config.php';

$vendor = mp_require_vendor();
$items = mp_order_items_for_vendor($vendor['id']);

$pageTitle = 'Orders';
$theme = 'main';
require __DIR__ . '/../templates/header.php';

$nextStatus = [
    'pending'    => 'processing',
    'processing' => 'shipped',
    'shipped'    => 'delivered',
];
$nextLabel = [
    'pending'    => 'Start Processing',
    'processing' => 'Mark Shipped',
    'shipped'    => 'Mark Delivered',
];
?>

<h1>Orders</h1>

<?php if (!$items): ?>
    <div class="empty-state reveal">
        <span class="empty-state-icon">📦</span>
        <h2>No orders yet</h2>
        <p>Orders containing your products will show up here.</p>
    </div>
<?php else: ?>
    <div class="content-panel">
        <table class="admin-table">
            <thead><tr><th>Order</th><th>Placed</th><th>Product</th><th>Qty</th><th>Total</th><th>Ships To</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><a href="<?= mp_e(ROUTE_ORDERS) ?>details.php?number=<?= mp_e($item['order_number']) ?>"><?= mp_e($item['order_number']) ?></a></td>
                    <td><?= mp_e(date('M j, Y', strtotime($item['placed_at']))) ?></td>
                    <td><?= mp_e($item['product_title']) ?></td>
                    <td><?= (int) $item['quantity'] ?></td>
                    <td><?= mp_currency((float) $item['line_total']) ?></td>
                    <td><?= mp_e($item['shipping_city']) ?>, <?= mp_e($item['shipping_country']) ?></td>
                    <td><span class="status-chip status-<?= mp_e($item['status']) ?>"><?= mp_e(ucfirst($item['status'])) ?></span></td>
                    <td>
                        <?php if (isset($nextStatus[$item['status']])): ?>
                        <form method="post" action="order-item-status.php" class="inline-form">
                            <?= mp_csrf_field() ?>
                            <input type="hidden" name="order_item_id" value="<?= (int) $item['id'] ?>">
                            <input type="hidden" name="status" value="<?= mp_e($nextStatus[$item['status']]) ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= mp_e($nextLabel[$item['status']]) ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
