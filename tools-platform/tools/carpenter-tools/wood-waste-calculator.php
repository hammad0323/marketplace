<div class="mb-3">
  <label for="f_net">Net Material Needed (board feet or sq ft)</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_net" placeholder="e.g. 120">
</div>
<div class="mb-3">
  <label for="f_waste">Expected Waste (%)</label>
  <input type="number" step="1" min="0" max="100" class="form-control" id="f_waste" value="15">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Gross Material Needed</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const net = tpValidateNumber(document.getElementById('f_net').value, { label: 'Net material needed', min: 0 });
    const waste = tpValidateNumber(document.getElementById('f_waste').value, { label: 'Waste %', min: 0, max: 100 });

    const gross = net / (1 - waste / 100);
    const wasteAmount = gross - net;

    tpShowResult(gross.toFixed(2) + ' (gross)', {
      label: `Buy ${wasteAmount.toFixed(2)} extra to cover ${waste}% waste`, raw: gross.toFixed(2), historyLabel: 'Gross Material',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
