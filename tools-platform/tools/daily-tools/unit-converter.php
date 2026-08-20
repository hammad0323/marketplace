<div class="mb-3">
  <label for="f_category">Category</label>
  <select id="f_category" class="form-select">
    <option value="length">Length</option>
    <option value="weight">Weight</option>
    <option value="temperature">Temperature</option>
    <option value="area">Area</option>
    <option value="volume">Volume</option>
  </select>
</div>
<div class="row g-2 mb-3">
  <div class="col-6"><label for="f_from">From</label><select id="f_from" class="form-select"></select></div>
  <div class="col-6"><label for="f_to">To</label><select id="f_to" class="form-select"></select></div>
</div>
<div class="mb-3">
  <label for="f_value">Value</label>
  <input type="number" step="any" class="form-control" id="f_value" placeholder="e.g. 10">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert</button>
<script>
const tpUnits = {
  length: { m: 1, km: 1000, cm: 0.01, mm: 0.001, mi: 1609.344, yd: 0.9144, ft: 0.3048, in: 0.0254 },
  weight: { kg: 1, g: 0.001, mg: 0.000001, lb: 0.453592, oz: 0.0283495, ton: 1000 },
  area: { sqm: 1, sqkm: 1000000, sqft: 0.092903, sqyd: 0.836127, acre: 4046.86, hectare: 10000 },
  volume: { l: 1, ml: 0.001, m3: 1000, gal: 3.78541, qt: 0.946353, cup: 0.24, ft3: 28.3168 },
};
function tpPopulateUnits() {
  const cat = document.getElementById('f_category').value;
  const fromSel = document.getElementById('f_from');
  const toSel = document.getElementById('f_to');
  fromSel.innerHTML = ''; toSel.innerHTML = '';
  if (cat === 'temperature') {
    ['Celsius', 'Fahrenheit', 'Kelvin'].forEach((u) => {
      fromSel.add(new Option(u, u)); toSel.add(new Option(u, u));
    });
    toSel.selectedIndex = 1;
  } else {
    Object.keys(tpUnits[cat]).forEach((u) => {
      fromSel.add(new Option(u, u)); toSel.add(new Option(u, u));
    });
    toSel.selectedIndex = 1;
  }
}
document.getElementById('f_category').addEventListener('change', tpPopulateUnits);
tpPopulateUnits();

function tpConvertTemp(value, from, to) {
  let celsius;
  if (from === 'Celsius') celsius = value;
  else if (from === 'Fahrenheit') celsius = (value - 32) * 5 / 9;
  else celsius = value - 273.15;

  if (to === 'Celsius') return celsius;
  if (to === 'Fahrenheit') return celsius * 9 / 5 + 32;
  return celsius + 273.15;
}

document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const cat = document.getElementById('f_category').value;
    const from = document.getElementById('f_from').value;
    const to = document.getElementById('f_to').value;
    const value = tpValidateNumber(document.getElementById('f_value').value, { label: 'Value' });

    let result;
    if (cat === 'temperature') {
      result = tpConvertTemp(value, from, to);
    } else {
      const baseValue = value * tpUnits[cat][from];
      result = baseValue / tpUnits[cat][to];
    }

    tpShowResult(result.toLocaleString(undefined, { maximumFractionDigits: 6 }) + ' ' + to, {
      label: `${value} ${from} =`, raw: result, historyLabel: 'Unit Conversion',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
