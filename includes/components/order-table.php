<?php if (!$orders): ?>
    <p class="text-muted mt-3 mb-0">You have not placed any orders yet. <a href="<?= e(url('shop')) ?>">Start shopping</a></p>
<?php else: ?>
<div class="table-responsive mt-3">
    <table class="table uc-table align-middle">
        <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Payment</th><th class="text-end">Total</th><th></th></tr></thead>
        <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?= e($o['order_number']) ?></strong></td>
                    <td><?= e(format_date($o['created_at'])) ?></td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td><?= status_badge($o['payment_status']) ?></td>
                    <td class="text-end"><?= e(money($o['grand_total'])) ?></td>
                    <td class="text-end"><a href="<?= e(url('order/' . $o['order_number'] . '?t=' . $o['access_token'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
