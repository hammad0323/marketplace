<?php
require __DIR__ . '/includes/admin.php';
require_section('payments');

$keys = [
    'pay_cod_enabled', 'pay_cod_title', 'pay_cod_note', 'pay_cod_fee', 'pay_cod_max',
    'pay_card_enabled', 'pay_card_title', 'pay_card_note', 'pay_card_mode', 'pay_card_bank_name', 'pay_card_account_title', 'pay_card_account_no', 'pay_card_iban',
    'pay_jazzcash_enabled', 'pay_jazzcash_title', 'pay_jazzcash_note', 'pay_jazzcash_mode', 'pay_jazzcash_account_title', 'pay_jazzcash_account_no', 'pay_jazzcash_merchant_id', 'pay_jazzcash_password', 'pay_jazzcash_salt', 'pay_jazzcash_sandbox',
    'pay_easypaisa_enabled', 'pay_easypaisa_title', 'pay_easypaisa_note', 'pay_easypaisa_mode', 'pay_easypaisa_account_title', 'pay_easypaisa_account_no', 'pay_easypaisa_store_id', 'pay_easypaisa_hash_key', 'pay_easypaisa_sandbox',
];
if (is_post()) {
    require_csrf();
    // Keep stored secrets when the field is left blank
    foreach (['pay_jazzcash_password', 'pay_jazzcash_salt', 'pay_easypaisa_hash_key'] as $secret) {
        if (($_POST[$secret] ?? '') === '') unset($_POST[$secret]);
    }
    save_posted_settings($keys);
    flash('success', 'Payment settings saved.');
    redirect('admin/payments');
}
$s = fn($k, $d = '') => setting($k, $d);
$secret = fn($k) => $s($k) !== '' ? 'Saved — leave blank to keep' : '';
admin_header('Payments', 'payments');
?>
<div class="note">Every method can be switched on/off. <b>Manual</b> mode shows your account number at checkout and asks the customer for the Transaction ID (you verify and mark the order Paid). <b>Gateway</b> mode redirects to the official JazzCash / EasyPaisa hosted checkout using your merchant credentials — test in Sandbox first.</div>
<form method="post" class="grid-2">
  <?= csrf_field() ?>

  <div class="card pay-card">
    <div class="card__head"><h3><?= aicon('card') ?> Cash on Delivery</h3><?= f_switch('pay_cod_enabled', 'Enabled', $s('pay_cod_enabled') === '1') ?></div>
    <?= f_text('pay_cod_title', 'Title at checkout', $s('pay_cod_title', 'Cash on Delivery')) ?>
    <?= f_text('pay_cod_note', 'Description', $s('pay_cod_note')) ?>
    <div class="row-2">
      <?= f_text('pay_cod_fee', 'COD extra fee', $s('pay_cod_fee', 0), ['type' => 'number', 'attrs' => 'min="0" step="1"', 'help' => '0 for no fee']) ?>
      <?= f_text('pay_cod_max', 'Max order total for COD', $s('pay_cod_max', 0), ['type' => 'number', 'attrs' => 'min="0"', 'help' => '0 = no limit']) ?>
    </div>
  </div>

  <div class="card pay-card">
    <div class="card__head"><h3><?= aicon('card') ?> Debit / Credit Card</h3><?= f_switch('pay_card_enabled', 'Enabled', $s('pay_card_enabled') === '1') ?></div>
    <?= f_text('pay_card_title', 'Title at checkout', $s('pay_card_title', 'Debit / Credit Card')) ?>
    <?= f_text('pay_card_note', 'Description', $s('pay_card_note')) ?>
    <?= f_select('pay_card_mode', 'Process cards through', $s('pay_card_mode', 'bank'), [
        'jazzcash' => 'JazzCash card gateway (uses JazzCash merchant details below)',
        'easypaisa' => 'EasyPaisa card gateway (uses EasyPaisa merchant details below)',
        'bank' => 'Manual — bank transfer / card payment to my bank account',
    ]) ?>
    <div class="row-2"><?= f_text('pay_card_bank_name', 'Bank name', $s('pay_card_bank_name')) ?><?= f_text('pay_card_account_title', 'Account title', $s('pay_card_account_title')) ?></div>
    <div class="row-2"><?= f_text('pay_card_account_no', 'Account number', $s('pay_card_account_no')) ?><?= f_text('pay_card_iban', 'IBAN', $s('pay_card_iban')) ?></div>
    <small class="help">Bank details are shown when manual mode is active (or if the selected gateway is not configured).</small>
  </div>

  <div class="card pay-card">
    <div class="card__head"><h3><?= aicon('card') ?> JazzCash</h3><?= f_switch('pay_jazzcash_enabled', 'Enabled', $s('pay_jazzcash_enabled') === '1') ?></div>
    <?= f_text('pay_jazzcash_title', 'Title at checkout', $s('pay_jazzcash_title', 'JazzCash')) ?>
    <?= f_text('pay_jazzcash_note', 'Description', $s('pay_jazzcash_note')) ?>
    <?= f_select('pay_jazzcash_mode', 'Mode', $s('pay_jazzcash_mode', 'manual'), ['manual' => 'Manual (send to my JazzCash account + TID)', 'gateway' => 'Merchant gateway (automatic)']) ?>
    <fieldset class="sub"><legend>Manual account</legend>
      <div class="row-2"><?= f_text('pay_jazzcash_account_title', 'Account title', $s('pay_jazzcash_account_title')) ?><?= f_text('pay_jazzcash_account_no', 'JazzCash number', $s('pay_jazzcash_account_no')) ?></div>
    </fieldset>
    <fieldset class="sub"><legend>Merchant account (Page Redirection v1.1)</legend>
      <?= f_text('pay_jazzcash_merchant_id', 'Merchant ID', $s('pay_jazzcash_merchant_id')) ?>
      <div class="row-2">
        <?= f_text('pay_jazzcash_password', 'Password', '', ['type' => 'password', 'attrs' => 'autocomplete="new-password" placeholder="' . e($secret('pay_jazzcash_password')) . '"']) ?>
        <?= f_text('pay_jazzcash_salt', 'Integrity salt', '', ['type' => 'password', 'attrs' => 'autocomplete="new-password" placeholder="' . e($secret('pay_jazzcash_salt')) . '"']) ?>
      </div>
      <?= f_switch('pay_jazzcash_sandbox', 'Sandbox / test mode', $s('pay_jazzcash_sandbox', '1') === '1') ?>
      <small class="help">Return URL to register in the JazzCash portal: <code><?= e(abs_url('payment-return?gw=jazzcash')) ?></code></small>
    </fieldset>
  </div>

  <div class="card pay-card">
    <div class="card__head"><h3><?= aicon('card') ?> EasyPaisa</h3><?= f_switch('pay_easypaisa_enabled', 'Enabled', $s('pay_easypaisa_enabled') === '1') ?></div>
    <?= f_text('pay_easypaisa_title', 'Title at checkout', $s('pay_easypaisa_title', 'EasyPaisa')) ?>
    <?= f_text('pay_easypaisa_note', 'Description', $s('pay_easypaisa_note')) ?>
    <?= f_select('pay_easypaisa_mode', 'Mode', $s('pay_easypaisa_mode', 'manual'), ['manual' => 'Manual (send to my EasyPaisa account + TID)', 'gateway' => 'Merchant gateway (Easypay hosted checkout)']) ?>
    <fieldset class="sub"><legend>Manual account</legend>
      <div class="row-2"><?= f_text('pay_easypaisa_account_title', 'Account title', $s('pay_easypaisa_account_title')) ?><?= f_text('pay_easypaisa_account_no', 'EasyPaisa number', $s('pay_easypaisa_account_no')) ?></div>
    </fieldset>
    <fieldset class="sub"><legend>Merchant account (Easypay)</legend>
      <div class="row-2">
        <?= f_text('pay_easypaisa_store_id', 'Store ID', $s('pay_easypaisa_store_id')) ?>
        <?= f_text('pay_easypaisa_hash_key', 'Hash key', '', ['type' => 'password', 'attrs' => 'autocomplete="new-password" placeholder="' . e($secret('pay_easypaisa_hash_key')) . '"']) ?>
      </div>
      <?= f_switch('pay_easypaisa_sandbox', 'Sandbox / test mode', $s('pay_easypaisa_sandbox', '1') === '1') ?>
      <small class="help">Postback URL: <code><?= e(abs_url('payment-return?gw=easypaisa&step=1')) ?></code>. EasyPaisa redirects are not signed, so successful payments are marked “Awaiting Verification” — confirm them in your Easypay merchant portal.</small>
    </fieldset>
  </div>

  <div class="save-bar span-2"><button class="btn btn-primary">Save Payment Settings</button></div>
</form>
<?php admin_footer();
