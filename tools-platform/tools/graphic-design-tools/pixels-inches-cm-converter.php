<div class="mb-3">
  <label for="f_value">Value</label>
  <input type="number" step="any" class="form-control" id="f_value" placeholder="e.g. 1920">
</div>
<div class="row g-2 mb-3">
  <div class="col-6">
    <label class="small">From</label>
    <select id="f_from" class="form-select">
      <option value="px">Pixels</option>
      <option value="in">Inches</option>
      <option value="cm">Centimeters</option>
      <option value="mm">Millimeters</option>
    </select>
  </div>
  <div class="col-6">
    <label class="small">To</label>
    <select id="f_to" class="form-select">
      <option value="px">Pixels</option>
      <option value="in" selected>Inches</option>
      <option value="cm">Centimeters</option>
      <option value="mm">Millimeters</option>
    </select>
  </div>
</div>
<div class="mb-3">
  <label for="f_dpi">DPI (for pixel conversions)</label>
  <input type="number" class="form-control" id="f_dpi" value="96" min="1">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const value = tpValidateNumber(document.getElementById('f_value').value, { label: 'Value' });
    const from = document.getElementById('f_from').value;
    const to = document.getElementById('f_to').value;
    const dpi = tpValidateNumber(document.getElementById('f_dpi').value, { label: 'DPI', min: 1 });

    let inches;
    if (from === 'px') inches = value / dpi;
    else if (from === 'in') inches = value;
    else if (from === 'cm') inches = value / 2.54;
    else inches = value / 25.4;

    let result;
    if (to === 'px') result = inches * dpi;
    else if (to === 'in') result = inches;
    else if (to === 'cm') result = inches * 2.54;
    else result = inches * 25.4;

    tpShowResult(result.toLocaleString(undefined, { maximumFractionDigits: 3 }) + ' ' + to, {
      label: `${value} ${from} =`, raw: result, historyLabel: 'Pixel Conversion',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
