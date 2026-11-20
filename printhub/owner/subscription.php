<?php
require __DIR__ . '/_init.php';
$sid = owner_shop_id();
$st = subscription_state($sid, $OWNER['shop']);
$periods = db_all('SELECT * FROM subscriptions WHERE shop_id = ? ORDER BY end_date DESC LIMIT 24', [$sid]);
$payments = db_all('SELECT * FROM subscription_payments WHERE shop_id = ? ORDER BY paid_on DESC LIMIT 24', [$sid]);
$sp = preg_replace('/\D/', '', (string) setting('support_phone'));
if (str_starts_with($sp, '0')) $sp = '92' . substr($sp, 1);
owner_header('Subscription');
?>
<div class="row g-3"><div class="col-xl-5"><div class="p-card"><div class="p-card-body">
    <div class="d-flex align-items-center gap-2 mb-3"><?= sub_badge($st['state']) ?><span class="text-muted small"><?= e($st['label']) ?></span></div>
    <dl class="p-kv"><dt>Plan</dt><dd><?= e(platform_name()) ?> monthly</dd><dt>Monthly price</dt><dd><strong><?= e(money($st['price'])) ?></strong></dd>
        <dt>Valid until</dt><dd><?= e(format_date($st['end_date'])) ?><?= $st['days_left'] !== null && $st['days_left'] >= 0 ? ' <span class="text-muted small">(' . $st['days_left'] . ' days left)</span>' : '' ?></dd>
        <?php if ($st['grace_until']): ?><dt>Grace period until</dt><dd><?= e(format_date($st['grace_until'])) ?></dd><?php endif; ?></dl>
    <hr><p class="small mb-2"><strong>How to renew:</strong> pay <?= e(money($st['price'])) ?> per month by cash, bank transfer, JazzCash or Easypaisa to <?= e(platform_name()) ?> and share the receipt. Your subscription is extended as soon as the payment is recorded.</p>
    <?php if ($sp): ?><a class="btn btn-success btn-sm" target="_blank" rel="noopener" href="https://wa.me/<?= e($sp) ?>?text=<?= rawurlencode('Hi, I want to renew the subscription for ' . $OWNER['shop']['name'] . ' (' . $OWNER['shop']['shop_code'] . ').') ?>"><i class="bi bi-whatsapp"></i> Contact support</a><?php endif; ?>
    <?php if (setting('support_email')): ?><a class="btn btn-light btn-sm" href="mailto:<?= e(setting('support_email')) ?>"><i class="bi bi-envelope"></i> <?= e(setting('support_email')) ?></a><?php endif; ?>
</div></div></div>
<div class="col-xl-7"><div class="p-card"><div class="p-card-head">Subscription periods</div><div class="table-responsive"><table class="table p-table"><thead><tr><th>Period</th><th>Months</th><th>Price / month</th><th>Status</th></tr></thead><tbody>
<?php foreach ($periods as $p): ?><tr><td><?= e(format_date($p['start_date'])) ?> – <?= e(format_date($p['end_date'])) ?></td><td><?= (int) $p['months'] ?></td><td><?= e(money($p['price'])) ?></td><td><?= e($p['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$periods): ?><tr><td colspan="4" class="text-muted small">No subscription yet.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="p-card"><div class="p-card-head">Payments</div><div class="table-responsive"><table class="table p-table"><thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead><tbody>
<?php foreach ($payments as $p): ?><tr><td><?= e(format_date($p['paid_on'])) ?></td><td><?= e(money($p['amount'])) ?></td><td><?= e(payment_methods()[$p['method']]) ?></td><td><?= e($p['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$payments): ?><tr><td colspan="4" class="text-muted small">No payments recorded.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
<?php owner_footer();
