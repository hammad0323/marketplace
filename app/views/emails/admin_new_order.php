<h2 style="font-family:Georgia,serif;font-weight:normal;color:#101D35;margin:0 0 12px;">New order <?= e($order['order_number']) ?></h2>
<p><?= e($order['customer_name']) ?> · <?= e($order['email']) ?> · <?= e($order['phone']) ?><br>
Payment: <?= e(payment_method_label($order['payment_method'])) ?> (<?= e(status_label($order['payment_status'])) ?>)</p>
<?php require __DIR__ . '/_items.php'; ?>
<?php if ($address): ?><p><?= e($address['address_line1']) ?>, <?= e($address['city']) ?>, <?= e($address['region']) ?></p><?php endif; ?>
<?php if ($order['customer_note']): ?><p><strong>Customer note:</strong> <?= nl2br(e($order['customer_note'])) ?></p><?php endif; ?>
<p><a href="<?= e(url('admin/order-view', ['id' => $order['id']])) ?>">Open in admin</a></p>
