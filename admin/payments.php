<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('payments.manage');
$defs = gateway_definitions();
if (is_post()) {
    require_csrf();
    $code = input('code');
    $gw = payment_gateway($code);
    if (!$gw || !isset($defs[$code])) {
        flash('error', 'Unknown gateway.');
        redirect(admin_url('payments'));
    }
    if (app_key_is_default() && $code !== 'cod') {
        flash('error', 'Set a unique APP_KEY in your configuration file before saving payment credentials.');
        redirect(admin_url('payments'));
    }
    $stored = json_decode((string) $gw['config'], true) ?: [];
    $cfg = [];
    $changed = [];
    foreach ($defs[$code]['fields'] as $key => $f) {
        $val = trim((string) ($_POST['cfg'][$key] ?? ''));
        if ($f['type'] === 'secret') {
            if (!empty($_POST['cfg_clear'][$key])) {
                $cfg[$key] = '';
                $changed[] = $key;
            } elseif ($val === '') {
                $cfg[$key] = $stored[$key] ?? '';      // keep existing encrypted value
            } else {
                $cfg[$key] = encrypt_secret($val);
                $changed[] = $key;
            }
            continue;
        }
        if ($f['type'] === 'url' && $val !== '' && !preg_match('#^https://#i', $val)) {
            flash('error', $f['label'] . ' must be an https:// URL.');
            redirect(admin_url('payments') . '#gw-' . $code);
        }
        if ($f['type'] === 'number') {
            $val = $val === '' ? '' : (string) max(0, (float) $val);
        }
        if ($f['type'] === 'select' && !array_key_exists($val, $f['options'])) {
            $val = $f['default'] ?? '';
        }
        if (($stored[$key] ?? '') !== $val) {
            $changed[] = $key;
        }
        $cfg[$key] = mb_substr($val, 0, 500);
    }
    db_update('payment_gateways', [
        'display_name' => mb_substr(input('display_name') ?: $gw['display_name'], 0, 80),
        'description' => mb_substr(input('description'), 0, 255) ?: null,
        'is_enabled' => input_bool('is_enabled'),
        'mode' => input('mode') === 'live' ? 'live' : 'sandbox',
        'sort_order' => input_int('sort_order'),
        'config' => json_encode($cfg, JSON_UNESCAPED_SLASHES),
    ], 'code = ?', [$code]);
    audit_log('payment_gateway_updated', 'payment_gateway', null, ['code' => $code, 'enabled' => input_bool('is_enabled'), 'mode' => input('mode'), 'changed_fields' => $changed]);
    flash('success', $gw['display_name'] . ' settings saved.' . (input_bool('is_enabled') && !gateway_is_configured_fresh($code) ? ' Note: required credentials are missing, so it stays hidden at checkout.' : ''));
    redirect(admin_url('payments') . '#gw-' . $code);
}

function gateway_is_configured_fresh(string $code): bool
{
    $gw = db_one('SELECT * FROM payment_gateways WHERE code = ?', [$code]);
    if ($code === 'cod') {
        return true;
    }
    if ($code === 'card') {
        $p = json_decode((string) $gw['config'], true)['provider'] ?? '';
        return in_array($p, ['jazzcash', 'easypaisa'], true) && gateway_is_configured_fresh($p);
    }
    $cfg = json_decode((string) $gw['config'], true) ?: [];
    foreach (gateway_definitions()[$code]['fields'] as $k => $f) {
        if (!empty($f['required']) && trim((string) ($cfg[$k] ?? '')) === '') {
            return false;
        }
    }
    return true;
}

$gateways = db_all('SELECT * FROM payment_gateways ORDER BY sort_order');
$callbacks = [
    'jazzcash' => ['Return URL (pp_ReturnURL)' => url('payment/jazzcash/return'), 'IPN URL (configure in JazzCash merchant portal)' => url('payment/jazzcash/ipn')],
    'easypaisa' => ['Post-back URL' => url('payment/easypaisa/return'), 'Confirm post-back URL' => url('payment/easypaisa/complete'), 'IPN listener URL (give to Easypaisa)' => url('payment/easypaisa/ipn')],
];
admin_header('Payment methods', 'payments');
?>
<div class="alert alert-info small"><i class="bi bi-shield-lock"></i> Credentials are encrypted with your APP_KEY and never displayed again or sent to the browser. Online payments are confirmed server-side (signed notification or status inquiry) — a browser redirect alone never marks an order as paid.
<strong>Gateways stay hidden at checkout until enabled and fully configured.</strong> Test every gateway end-to-end in sandbox with your provider before switching to live — see <code>docs/PAYMENT-GATEWAYS.md</code>.</div>
<?php foreach ($gateways as $gw): $code = $gw['code']; $def = $defs[$code]; $cfg = gateway_config($gw); $configured = gateway_is_configured($code); $stored = json_decode((string) $gw['config'], true) ?: []; ?>
<div class="card mb-3" id="gw-<?= e($code) ?>"><div class="card-header d-flex flex-wrap gap-2 align-items-center">
  <span><?= e($def['label']) ?></span>
  <?= $gw['is_enabled'] ? '<span class="badge text-bg-success">Enabled</span>' : '<span class="badge text-bg-secondary">Disabled</span>' ?>
  <?php if ($code !== 'cod'): ?><?= $configured ? '<span class="badge text-bg-info">Credentials saved</span>' : '<span class="badge text-bg-warning">Not configured</span>' ?> <span class="badge text-bg-<?= $gw['mode'] === 'live' ? 'danger' : 'light' ?>"><?= e(ucfirst($gw['mode'])) ?></span><?php endif; ?>
  <?php if ($code !== 'cod'): ?><small class="text-muted ms-auto">Integration-ready — verify with your provider's sandbox before going live.</small><?php endif; ?>
</div><div class="card-body">
  <form method="post"><?= csrf_field() ?><input type="hidden" name="code" value="<?= e($code) ?>">
  <div class="row">
    <div class="col-md-3"><?= f_check('is_enabled', 'Enabled at checkout', (int) $gw['is_enabled']) ?></div>
    <?php if ($code !== 'cod'): ?><div class="col-md-3"><?= f_select('mode', 'Mode', ['sandbox' => 'Sandbox / test', 'live' => 'Live'], $gw['mode']) ?></div><?php else: ?><input type="hidden" name="mode" value="live"><?php endif; ?>
    <div class="col-md-3"><?= f_text('display_name', 'Name at checkout', $gw['display_name']) ?></div>
    <div class="col-md-3"><?= f_number('sort_order', 'Order', $gw['sort_order'], ['step' => 1]) ?></div>
    <div class="col-12"><?= f_text('description', 'Description at checkout', $gw['description']) ?></div>
    <?php foreach ($def['fields'] as $key => $f): $name = 'cfg[' . $key . ']'; ?>
      <div class="col-md-6">
        <?php if ($f['type'] === 'secret'): $has = !empty($stored[$key]); ?>
          <div class="mb-3"><label class="form-label"><?= e($f['label']) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
            <input type="password" class="form-control" name="<?= e($name) ?>" value="" autocomplete="new-password" placeholder="<?= $has ? '•••••••• saved — leave blank to keep' : 'Not set' ?>">
            <?php if ($has): ?><label class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="cfg_clear[<?= e($key) ?>]" value="1"> Clear saved value</label><?php endif; ?>
            <?= f_help($f['help'] ?? null) ?></div>
        <?php elseif ($f['type'] === 'select'): ?>
          <?= f_select($name, $f['label'], $f['options'], $cfg[$key] ?? '', [], $f['help'] ?? null) ?>
        <?php else: ?>
          <?= $f['type'] === 'number' ? f_number($name, $f['label'], $cfg[$key] ?? '', ['min' => 0], $f['help'] ?? null) : f_text($name, $f['label'] . (!empty($f['required']) ? ' *' : ''), $cfg[$key] ?? '', [], $f['help'] ?? null) ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (isset($callbacks[$code])): ?>
    <div class="bg-light rounded p-3 mb-3 small"><strong>URLs to register with <?= e($gw['display_name']) ?>:</strong>
      <?php foreach ($callbacks[$code] as $label => $u): ?><div><?= e($label) ?>: <code><?= e($u) ?></code></div><?php endforeach; ?></div>
  <?php endif; ?>
  <?= f_submit('Save ' . $gw['display_name']) ?>
  </form>
</div></div>
<?php endforeach; ?>
<?php admin_footer();
