<div class="mb-3">
  <label for="f_avg_order">Average Order Value</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_avg_order" placeholder="e.g. 3000">
</div>
<div class="mb-3">
  <label for="f_frequency">Purchases per Year (per customer)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_frequency" placeholder="e.g. 4">
</div>
<div class="mb-3">
  <label for="f_years">Average Years a Customer Stays</label>
  <input type="number" step="0.1" min="0" class="form-control" id="f_years" placeholder="e.g. 3">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Lifetime Value</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const avgOrder = tpValidateNumber(document.getElementById('f_avg_order').value, { label: 'Average order value', min: 0 });
    const frequency = tpValidateNumber(document.getElementById('f_frequency').value, { label: 'Purchases per year', min: 0 });
    const years = tpValidateNumber(document.getElementById('f_years').value, { label: 'Customer lifespan (years)', min: 0 });

    const clv = avgOrder * frequency * years;

    tpShowResult(tpFormatMoney(clv), {
      label: `Customer Lifetime Value — ${tpFormatMoney(avgOrder)} × ${frequency}/year × ${years} year(s)`,
      raw: clv.toFixed(2), historyLabel: 'Customer Lifetime Value',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
