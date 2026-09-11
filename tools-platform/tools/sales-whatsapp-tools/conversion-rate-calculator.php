<div class="mb-3">
  <label for="f_leads">Total Leads / Visitors</label>
  <input type="number" step="1" min="1" class="form-control" id="f_leads" placeholder="e.g. 500">
</div>
<div class="mb-3">
  <label for="f_conversions">Converted to Customers</label>
  <input type="number" step="1" min="0" class="form-control" id="f_conversions" placeholder="e.g. 45">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Conversion Rate</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const leads = tpValidateNumber(document.getElementById('f_leads').value, { label: 'Total leads', min: 1 });
    const conversions = tpValidateNumber(document.getElementById('f_conversions').value, { label: 'Converted customers', min: 0, max: leads });

    const rate = (conversions / leads) * 100;

    tpShowResult(rate.toFixed(2) + '%', {
      label: `Conversion Rate — ${conversions} of ${leads} lead(s) converted`,
      raw: rate.toFixed(2), historyLabel: 'Conversion Rate',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
