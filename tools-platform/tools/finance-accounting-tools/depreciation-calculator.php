<div class="mb-3">
  <label for="f_cost">Asset Cost</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_cost" placeholder="e.g. 50000">
</div>
<div class="mb-3">
  <label for="f_salvage">Salvage Value</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_salvage" value="0">
</div>
<div class="mb-3">
  <label for="f_life">Useful Life (years)</label>
  <input type="number" step="1" min="1" class="form-control" id="f_life" placeholder="e.g. 10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Straight-Line Depreciation</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const cost = tpValidateNumber(document.getElementById('f_cost').value, { label: 'Asset cost', min: 0 });
    const salvage = tpValidateNumber(document.getElementById('f_salvage').value || 0, { label: 'Salvage value', min: 0 });
    const life = tpValidateNumber(document.getElementById('f_life').value, { label: 'Useful life', min: 1 });
    if (salvage > cost) throw new Error('Salvage value cannot exceed asset cost.');

    const depreciableBase = cost - salvage;
    const annualDepreciation = depreciableBase / life;
    const rate = (annualDepreciation / cost) * 100;

    tpShowResult(tpFormatMoney(annualDepreciation) + ' / year', {
      label: `${rate.toFixed(1)}% of cost per year over ${life} years`, raw: annualDepreciation.toFixed(2), historyLabel: 'Annual Depreciation',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
