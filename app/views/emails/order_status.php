<h1 style="font-family:Georgia,serif;font-weight:normal;color:#101D35;font-size:24px;margin:0 0 12px;">Order <?= e($order['order_number']) ?> — <?= e(status_label($order['status'])) ?></h1>
<?php if ($order['status'] === 'shipped'): ?>
<p>Good news — your order is on its way.</p>
<?php if ($order['courier_name'] || $order['tracking_number']): ?><p>Courier: <strong><?= e($order['courier_name']) ?></strong><br>Tracking number: <strong><?= e($order['tracking_number']) ?></strong><?php if ($order['tracking_url']): ?><br><a href="<?= e($order['tracking_url']) ?>">Track your parcel</a><?php endif; ?></p><?php endif; ?>
<?php elseif ($order['status'] === 'delivered'): ?>
<p>Your order has been delivered. We hope you enjoy it — if anything is not right, simply reply to this email.</p>
<?php elseif ($order['status'] === 'cancelled'): ?>
<p>Your order has been cancelled. If you did not request this or have any questions, please contact us.</p>
<?php elseif ($order['status'] === 'confirmed'): ?>
<p>Your order has been confirmed and is being prepared.</p>
<?php endif; ?>
<?php if (!empty($note)): ?><p style="background:#F7F5F0;padding:12px 16px;border-left:3px solid #B99A5B;"><?= nl2br(e($note)) ?></p><?php endif; ?>
<p style="text-align:center;margin:28px 0;"><a href="<?= e($link) ?>" style="background:#214E9B;color:#fff;text-decoration:none;padding:12px 28px;display:inline-block;">Track your order</a></p>
