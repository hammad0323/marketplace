<div class="mb-3">
  <label for="f_board_feet">Board Feet Needed</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_board_feet" placeholder="e.g. 45">
</div>
<div class="mb-3">
  <label for="f_price">Price per Board Foot</label>
  <input type="number" step="0.01" min="0" class="form-control" id="f_price" placeholder="e.g. 6.50">
</div>
<div class="mb-3">
  <label for="f_waste">Waste Allowance (%)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_waste" value="10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Total Wood Cost</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const boardFeet = tpValidateNumber(document.getElementById('f_board_feet').value, { label: 'Board feet', min: 0 });
    const price = tpValidateNumber(document.getElementById('f_price').value, { label: 'Price per board foot', min: 0 });
    const waste = tpValidateNumber(document.getElementById('f_waste').value || 0, { label: 'Waste allowance', min: 0 });

    const totalBoardFeet = boardFeet * (1 + waste / 100);
    const totalCost = totalBoardFeet * price;

    tpShowResult('$' + totalCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), {
      label: `${totalBoardFeet.toFixed(1)} board feet including ${waste}% waste`, raw: totalCost.toFixed(2), historyLabel: 'Wood Cost',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
