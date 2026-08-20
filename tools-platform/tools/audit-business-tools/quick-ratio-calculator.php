<div class="mb-3">
  <label for="f_assets">Current Assets</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_assets" placeholder="e.g. 150000">
</div>
<div class="mb-3">
  <label for="f_inventory">Inventory</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_inventory" placeholder="e.g. 40000">
</div>
<div class="mb-3">
  <label for="f_liabilities">Current Liabilities</label>
  <input type="number" step="0.01" min="0.01" class="form-control" id="f_liabilities" placeholder="e.g. 75000">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Quick Ratio</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const assets = tpValidateNumber(document.getElementById('f_assets').value, { label: 'Current assets', min: 0 });
    const inventory = tpValidateNumber(document.getElementById('f_inventory').value, { label: 'Inventory', min: 0 });
    const liabilities = tpValidateNumber(document.getElementById('f_liabilities').value, { label: 'Current liabilities', min: 0.01 });

    if (inventory > assets) throw new Error('Inventory cannot be greater than total current assets.');
    const ratio = (assets - inventory) / liabilities;

    tpShowResult(ratio.toFixed(2), {
      label: 'Quick Ratio (Acid-Test)', raw: ratio.toFixed(4), historyLabel: 'Quick Ratio',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
