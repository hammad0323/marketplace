<div class="mb-3">
  <label for="f_net_income">Net Income</label>
  <input type="number" step="0.01" class="form-control" id="f_net_income" placeholder="e.g. 90000">
</div>
<div class="mb-3">
  <label for="f_equity">Average Shareholders' Equity</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_equity" placeholder="e.g. 600000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate ROE</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const netIncome = tpValidateNumber(document.getElementById('f_net_income').value, { label: 'Net income' });
    const equity = tpValidateNumber(document.getElementById('f_equity').value, { label: "Shareholders' equity", min: 0.01 });

    const roe = (netIncome / equity) * 100;

    tpShowResult(roe.toFixed(2) + '%', {
      label: 'Return on Equity (ROE)', raw: roe.toFixed(3), historyLabel: 'ROE',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
