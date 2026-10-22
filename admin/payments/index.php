<?php
/** Payment method configuration. Credentials are encrypted at rest with APP_KEY and never shown back in full. */
require __DIR__ . '/../includes/init.php';
admin_require('payments');
$fields = payment_gateway_fields();
$code = input('code', '', 'get');
if (is_post()) {
    $code = input('code', '', 'post');
    $pm = payment_method_get($code);
    if (!$pm) redirect('admin/payments/');
    $creds = payment_credentials($pm);
    $changed = false;
    foreach ($fields[$code] ?? [] as $k => $f) {
        $v = trim((string) ($_POST['cred'][$k] ?? ''));
        if ($f['secret'] && $v === '') continue; // blank secret = keep existing
        if (($creds[$k] ?? '') !== $v) {
            $creds[$k] = mb_substr($v, 0, 500);
            $changed = true;
        }
    }
    $mode = input('mode', 'sandbox', 'post') === 'live' ? 'live' : 'sandbox';
    $verified = input_bool('is_verified');
    if ($changed || $mode !== $pm['mode']) {
        $verified = $code === 'cod' ? 1 : 0; // new credentials or mode must be re-tested
    }
    db_update('payment_methods', [
        'name' => mb_substr(input('name', $pm['name'], 'post'), 0, 80),
        'description' => mb_substr(input('description', '', 'post'), 0, 255),
        'is_enabled' => input_bool('is_enabled'),
        'is_verified' => $verified,
        'mode' => $code === 'cod' ? 'live' : $mode,
        'fee' => max(0, (float) input_float('fee', 0)),
        'min_order' => input_float('min_order'),
        'max_order' => input_float('max_order'),
        'sort_order' => input_int('sort_order'),
        'credentials_encrypted' => $creds ? encrypt_secret(json_encode($creds)) : null,
    ], 'code = ?', [$code]);
    if ($code === 'cod') {
        setting_save('cod_note', mb_substr(input('cod_note', '', 'post'), 0, 255), 'orders');
    }
    audit_log('payment_method_saved', 'payment_method', (int) $pm['id'], ['code' => $code, 'enabled' => input_bool('is_enabled'), 'mode' => $mode, 'verified' => $verified, 'credentials_changed' => $changed]);
    flash('success', $pm['name'] . ' saved.' . ($changed && $code !== 'cod' ? ' Credentials changed — test in sandbox, then tick "Testing completed" to make it available to customers.' : ''));
    redirect('admin/payments/?code=' . $code);
}
$methods = payment_methods_all();
$stateLabels = ['ready' => ['Available to customers', 'success'], 'testing' => ['Sandbox — admins only', 'info'], 'unverified' => ['Live — awaiting test confirmation', 'warning'],
    'unconfigured' => ['Credentials missing', 'danger'], 'disabled' => ['Disabled', 'secondary']];
admin_header('Payment methods');
?>
<div class="a-help mb-4"><strong>How online payments are protected:</strong> customers pay on the provider's hosted page; card numbers and CVVs never reach this server.
Orders are marked paid only after a <b>server-side verification</b> (signed callback / webhook plus a status inquiry) — never from a browser redirect alone. Duplicate callbacks are ignored and every gateway message is logged on the order.
<br><strong>Going live:</strong> enter merchant credentials → keep <b>Sandbox</b> mode and place test orders while signed in as admin → switch to <b>Live</b> → place a small real order → tick <b>Testing completed</b>. Until then the method shows as unavailable to customers.</div>
<div class="row g-4">
<?php foreach ($methods as $m): $st = payment_method_state($m); $creds = payment_credentials($m); $open = $code === $m['code']; ?>
<div class="col-xl-6"><div class="card a-card">
    <div class="card-header d-flex align-items-center gap-2"><?= e($m['name']) ?> <?= a_badge($stateLabels[$st][0], $stateLabels[$st][1]) ?>
        <a class="ms-auto small" data-bs-toggle="collapse" href="#pm<?= e($m['code']) ?>">Configure</a></div>
    <div class="collapse<?= $open ? ' show' : '' ?>" id="pm<?= e($m['code']) ?>"><div class="card-body">
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($m['code']) ?>">
        <div class="row"><div class="col-md-6"><?= a_input('name', 'Display name', $m['name']) ?></div><div class="col-md-6"><?= a_input('sort_order', 'Order', $m['sort_order'], 'number') ?></div></div>
        <?= a_input('description', 'Checkout description', $m['description']) ?>
        <div class="row">
            <div class="col-4"><?= a_input('fee', $m['code'] === 'cod' ? 'COD fee' : 'Payment fee', $m['fee'], 'number', ['step' => '1', 'min' => 0]) ?></div>
            <div class="col-4"><?= a_input('min_order', 'Min order', $m['min_order'], 'number', ['step' => '1']) ?></div>
            <div class="col-4"><?= a_input('max_order', 'Max order', $m['max_order'], 'number', ['step' => '1']) ?></div>
        </div>
        <?php if ($m['code'] === 'cod'): ?>
            <?= a_input('cod_note', 'Note shown on product pages', setting('cod_note')) ?>
            <p class="small text-muted">COD availability per area is set on each <a href="<?= e(url('admin/shipping/')) ?>">delivery zone</a>.</p>
        <?php else: ?>
            <?= a_select('mode', 'Mode', ['sandbox' => 'Sandbox / testing', 'live' => 'Live'], $m['mode']) ?>
            <?php foreach ($fields[$m['code']] as $k => $f): $has = trim((string) ($creds[$k] ?? '')) !== ''; ?>
                <div class="mb-3"><label class="form-label small"><?= e($f['label']) ?><?= $f['required'] ? ' *' : '' ?></label>
                <?php if ($f['secret']): ?>
                    <input class="form-control" type="password" name="cred[<?= e($k) ?>]" value="" placeholder="<?= $has ? '•••••••• saved (leave blank to keep)' : 'Not set' ?>" autocomplete="new-password">
                <?php else: ?>
                    <input class="form-control" name="cred[<?= e($k) ?>]" value="<?= e($creds[$k] ?? '') ?>">
                <?php endif; ?></div>
            <?php endforeach; ?>
            <div class="a-help small mb-3">
                <?php if ($m['code'] === 'jazzcash'): ?>Return URL: <code><?= e(url('api/payments/jazzcash-return.php')) ?></code><br>IPN URL (merchant portal): <code><?= e(url('api/payments/jazzcash-ipn.php')) ?></code>
                <?php elseif ($m['code'] === 'easypaisa'): ?>Post-back URL is sent automatically.<br>IPN URL (merchant portal): <code><?= e(url('api/payments/easypaisa-ipn.php')) ?></code>
                <?php else: ?>Webhook endpoint (Stripe dashboard): <code><?= e(url('api/payments/stripe-webhook.php')) ?></code><br>Events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired<?php endif; ?>
                <br>See <code>docs/PAYMENTS.md</code> for full setup steps.</div>
            <?= a_check('is_verified', 'Testing completed — make available to customers in Live mode', (int) $m['is_verified']) ?>
        <?php endif; ?>
        <?= a_check('is_enabled', 'Enabled', (int) $m['is_enabled']) ?>
        <button class="btn btn-dark">Save <?= e($m['name']) ?></button>
    </form>
    </div></div>
</div></div>
<?php endforeach; ?>
</div>
<?php admin_footer();
