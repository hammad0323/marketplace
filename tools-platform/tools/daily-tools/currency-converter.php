<?php
$tpCurrencyRates = tp_query('SELECT target_currency, rate, fetched_at FROM currency_rates ORDER BY target_currency ASC');
$tpCurrencyBase = tp_setting('currency_base', 'USD');
$tpLastUpdated = $tpCurrencyRates ? max(array_column($tpCurrencyRates, 'fetched_at')) : null;
?>
<?php if (!$tpCurrencyRates): ?>
  <div class="alert alert-warning">No exchange rates are configured yet. An admin can add a Currency API (or manual rates) under Admin → Settings → Currency API.</div>
<?php else: ?>
<div class="mb-3">
  <label for="f_amount">Amount</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_amount" placeholder="e.g. 100" value="1">
</div>
<div class="row g-2 mb-3">
  <div class="col-6">
    <label>From</label>
    <select id="f_from" class="form-select">
      <option value="<?= e($tpCurrencyBase) ?>"><?= e($tpCurrencyBase) ?> (base)</option>
      <?php foreach ($tpCurrencyRates as $r): ?>
        <option value="<?= e($r['target_currency']) ?>"><?= e($r['target_currency']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-6">
    <label>To</label>
    <select id="f_to" class="form-select">
      <?php foreach ($tpCurrencyRates as $r): ?>
        <option value="<?= e($r['target_currency']) ?>"><?= e($r['target_currency']) ?></option>
      <?php endforeach; ?>
      <option value="<?= e($tpCurrencyBase) ?>"><?= e($tpCurrencyBase) ?> (base)</option>
    </select>
  </div>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert</button>
<p class="text-muted small mt-2 mb-0">Rates last updated: <?= e($tpLastUpdated ?? 'never') ?> (<?= e(tp_setting('currency_update_frequency')) ?> updates). Not guaranteed to reflect the live market rate at the moment of your transaction.</p>
<script>
const tpRates = <?= json_encode(array_column($tpCurrencyRates, 'rate', 'target_currency')) ?>;
const tpBaseCurrency = <?= json_encode($tpCurrencyBase) ?>;

function tpToBase(amount, currency) {
  if (currency === tpBaseCurrency) return amount;
  return amount / parseFloat(tpRates[currency]);
}
function tpFromBase(amount, currency) {
  if (currency === tpBaseCurrency) return amount;
  return amount * parseFloat(tpRates[currency]);
}

document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const amount = tpValidateNumber(document.getElementById('f_amount').value, { label: 'Amount', min: 0 });
    const from = document.getElementById('f_from').value;
    const to = document.getElementById('f_to').value;

    const inBase = tpToBase(amount, from);
    const result = tpFromBase(inBase, to);

    tpShowResult(result.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 4 }) + ' ' + to, {
      label: `${amount} ${from} =`, raw: result, historyLabel: 'Currency Conversion',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
<?php endif; ?>
