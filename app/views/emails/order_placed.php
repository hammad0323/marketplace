<h1 style="font-family:Georgia,serif;font-weight:normal;color:#101D35;font-size:26px;margin:0 0 12px;">Thank you, <?= e(explode(' ', $order['customer_name'])[0]) ?>.</h1>
<p>We have received your order <strong><?= e($order['order_number']) ?></strong> placed on <?= e(format_date($order['created_at'], true)) ?>.</p>
<p>Payment: <strong><?= e(payment_method_label($order['payment_method'])) ?></strong> — <?= e(status_label($order['payment_status'])) ?><br>
<?php if ($order['delivery_estimate']): ?>Estimated delivery: <?= e($order['delivery_estimate']) ?><?php endif; ?></p>
<?php require __DIR__ . '/_items.php'; ?>
<?php if ($address): ?>
<p style="margin-top:20px;"><strong>Delivering to</strong><br><?= e($address['full_name']) ?><br><?= e($address['address_line1']) ?><?= $address['address_line2'] ? '<br>' . e($address['address_line2']) : '' ?><br><?= e($address['city']) ?>, <?= e($address['region']) ?> <?= e($address['postal_code']) ?><br><?= e($address['phone']) ?></p>
<?php endif; ?>
<p style="text-align:center;margin:28px 0;"><a href="<?= e($link) ?>" style="background:#214E9B;color:#fff;text-decoration:none;padding:12px 28px;display:inline-block;letter-spacing:1px;">View your order</a></p>
<p style="color:#6b7280;font-size:13px;">Keep this email — the link above lets you check your order status without an account.</p>
