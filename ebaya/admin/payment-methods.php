<?php
/** Gateway configuration. Secrets are encrypted at rest and never re-displayed. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('payments.manage');
$defs = payment_gateway_defs();

if (is_post()) {
    csrf_check();
    $code = post('code');
    if (!isset($defs[$code])) { flash('danger', 'Unknown gateway.'); redirect(admin_url('payment-methods')); }
    db_tx(function () use ($code, $defs) {
        $mode = post('mode') === 'live' ? 'live' : 'sandbox';
        $old = db_one('SELECT * FROM payment_gateways WHERE code = ?', [$code]);
        // Switching to live or changing credentials resets the "tested" confirmation.
        $credChanged = false;
        foreach ($defs[$code][1] as $k => [$label, $secret]) {
            $val = trim((string)($_POST['cfg'][$k] ?? ''));
            if ($secret) {
                if ($val === '' && empty($_POST['clear'][$k])) continue; // keep existing secret
                $store = $val === '' ? '' : encrypt_secret($val);
                $credChanged = true;
            } else {
                $prev = db_val('SELECT setting_value FROM payment_gateway_settings WHERE gateway_code = ? AND setting_key = ?', [$code, $k]);
                if ((string)$prev !== $val && $k !== 'instructions') $credChanged = true;
                $store = mb_substr($val, 0, 500);
            }
            db_exec('INSERT INTO payment_gateway_settings (gateway_code, setting_key, setting_value, is_secret) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_secret = VALUES(is_secret)',
                [$code, $k, $store, $secret ? 1 : 0]);
        }
        $tested = (post('tested') && !$credChanged) ? 1 : 0;
        db_exec('UPDATE payment_gateways SET name = ?, description = ?, enabled = ?, mode = ?, tested = ?, sort_order = ? WHERE code = ?', [
            mb_substr(post('name'), 0, 80) ?: $old['name'], mb_substr(post('description'), 0, 255), post('enabled') ? 1 : 0, $code === 'cod' ? 'live' : $mode, $code === 'cod' ? 1 : $tested, (int)post('sort_order'), $code]);
    });
    audit('gateway_update', 'payment_gateway', null, ['code' => $code, 'enabled' => (bool)post('enabled'), 'mode' => post('mode'), 'tested' => (bool)post('tested')]);
    flash('success', 'Payment method saved.');
    redirect(admin_url('payment-methods#g-' . $code));
}

$gateways = db_all('SELECT * FROM payment_gateways ORDER BY sort_order');
$callbacks = [
    'jazzcash' => ['Return URL' => abs_url('payment/jazzcash/return'), 'IPN URL' => abs_url('payment/jazzcash/ipn')],
    'easypaisa' => ['Postback URL' => abs_url('payment/easypaisa/return'), 'IPN URL' => abs_url('payment/easypaisa/ipn')],
    'card' => ['Webhook endpoint' => abs_url('payment/card/webhook') . ' (events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed)'],
];
$admin_title = 'Payment methods';
require __DIR__ . '/partials/header.php';
?>
<div class="alert alert-info small">Online gateways are shown to customers only when <strong>enabled</strong>, all required credentials are saved, mode is <strong>Live</strong>, and <strong>Testing completed</strong> is ticked. In Sandbox mode a configured gateway is visible only to signed-in admins so you can place test orders. Orders are marked paid only after server-side verification — never from the browser redirect alone. See docs/PAYMENTS.md.</div>
<?php foreach ($gateways as $g): $code = $g['code']; $gw = payment_gateway($code); [$stateLabel, $stateTone] = payment_gateway_state($code); ?>
<div class="card mb-3" id="g-<?= e($code) ?>">
  <div class="card-header d-flex align-items-center"><?= e($g['name']) ?> <span class="badge text-bg-<?= e($stateTone) ?> ms-2"><?= e($stateLabel) ?></span></div>
  <div class="card-body">
    <form method="post" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($code) ?>">
      <div class="row">
        <div class="col-md-4"><?= f_text('name', 'Name shown at checkout', $g['name']) ?></div>
        <div class="col-md-6"><?= f_text('description', 'Description shown at checkout', $g['description']) ?></div>
        <div class="col-md-2"><?= f_text('sort_order', 'Order', $g['sort_order'], ['type' => 'number']) ?></div>
        <?php foreach ($defs[$code][1] as $k => [$label, $secret, $req, $help]): $has = !empty($gw['config'][$k]); ?>
          <div class="col-md-6 mb-3">
            <label class="form-label"><?= e($label) ?><?= $req ? ' <span class="text-danger">*</span>' : '' ?></label>
            <?php if ($k === 'provider'): ?><input class="form-control" value="Stripe Checkout (hosted)" readonly><input type="hidden" name="cfg[provider]" value="stripe">
            <?php elseif ($secret): ?>
              <input class="form-control" type="password" name="cfg[<?= e($k) ?>]" placeholder="<?= $has ? '•••••••• saved — leave blank to keep' : 'Not set' ?>" autocomplete="new-password">
              <?php if ($has): ?><label class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="clear[<?= e($k) ?>]" value="1"> Clear saved value</label><?php endif; ?>
            <?php elseif ($k === 'instructions'): ?><textarea class="form-control" name="cfg[<?= e($k) ?>]" rows="2"><?= e($gw['config'][$k] ?? '') ?></textarea>
            <?php else: ?><input class="form-control" name="cfg[<?= e($k) ?>]" value="<?= e($gw['config'][$k] ?? '') ?>"><?php endif; ?>
            <?php if ($help): ?><div class="form-text"><?= e($help) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if (isset($callbacks[$code])): ?><div class="bg-light rounded p-2 small mb-3"><strong>Give these URLs to the gateway:</strong><br><?php foreach ($callbacks[$code] as $l => $u): ?><?= e($l) ?>: <code><?= e($u) ?></code><br><?php endforeach; ?></div><?php endif; ?>
      <div class="d-flex flex-wrap gap-4 align-items-center">
        <?= f_toggle('enabled', 'Enabled', $g['enabled'], ['wrap' => '']) ?>
        <?php if ($code !== 'cod'): ?>
          <div><label class="me-2 small">Mode</label><select name="mode" class="form-select form-select-sm d-inline-block w-auto"><option value="sandbox">Sandbox / testing</option><option value="live"<?= $g['mode'] === 'live' ? ' selected' : '' ?>>Live</option></select></div>
          <?= f_toggle('tested', 'Testing completed — allow live customers', $g['tested'], ['wrap' => '']) ?>
        <?php else: ?><span class="small text-muted">COD availability and fees are set per delivery zone in Shipping & delivery.</span><?php endif; ?>
        <button class="btn btn-primary ms-auto">Save</button>
      </div>
      <?php if ($code !== 'cod'): ?><div class="form-text">Changing credentials clears “Testing completed” so the gateway cannot go live untested.</div><?php endif; ?>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/partials/footer.php';
