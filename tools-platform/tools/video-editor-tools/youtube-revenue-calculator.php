<div class="mb-3">
  <label for="f_views">Monthly Views</label>
  <input type="number" step="1" min="0" class="form-control" id="f_views" placeholder="e.g. 100000">
</div>
<div class="mb-3">
  <label for="f_rpm">Estimated RPM (revenue per 1,000 views, USD)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_rpm" placeholder="e.g. 3.50" value="3">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Estimate Revenue</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const views = tpValidateNumber(document.getElementById('f_views').value, { label: 'Views', min: 0 });
    const rpm = tpValidateNumber(document.getElementById('f_rpm').value, { label: 'RPM', min: 0 });

    const revenue = (views / 1000) * rpm;

    tpShowResult('$' + revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), {
      label: 'Estimated Monthly Revenue (rough estimate — actual RPM varies by niche, region and season)',
      raw: revenue.toFixed(2), historyLabel: 'YouTube Revenue Estimate',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
