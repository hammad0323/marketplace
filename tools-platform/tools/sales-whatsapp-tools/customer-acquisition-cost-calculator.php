<div class="mb-3">
  <label for="f_spend">Total Sales & Marketing Spend</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_spend" placeholder="e.g. 100000">
</div>
<div class="mb-3">
  <label for="f_customers">New Customers Acquired</label>
  <input type="number" step="1" min="1" class="form-control" id="f_customers" placeholder="e.g. 40">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate CAC</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const spend = tpValidateNumber(document.getElementById('f_spend').value, { label: 'Total spend', min: 0 });
    const customers = tpValidateNumber(document.getElementById('f_customers').value, { label: 'New customers', min: 1 });

    const cac = spend / customers;

    tpShowResult(tpFormatMoney(cac), {
      label: `Customer Acquisition Cost — ${tpFormatMoney(spend)} spent to acquire ${customers} customer(s)`,
      raw: cac.toFixed(2), historyLabel: 'Customer Acquisition Cost',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
