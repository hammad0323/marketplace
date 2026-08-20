<div class="row g-2 mb-3">
  <div class="col-4"><label class="small">Pallet Length (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_pallet_l" value="48"></div>
  <div class="col-4"><label class="small">Pallet Width (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_pallet_w" value="40"></div>
  <div class="col-4"><label class="small">Max Stack Height (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_pallet_h" value="60"></div>
</div>
<div class="row g-2 mb-3">
  <div class="col-4"><label class="small">Carton Length (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_carton_l" placeholder="e.g. 12"></div>
  <div class="col-4"><label class="small">Carton Width (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_carton_w" placeholder="e.g. 10"></div>
  <div class="col-4"><label class="small">Carton Height (in)</label><input type="number" step="0.1" min="0" class="form-control" id="f_carton_h" placeholder="e.g. 8"></div>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Cartons per Pallet</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const pl = tpValidateNumber(document.getElementById('f_pallet_l').value, { label: 'Pallet length', min: 0.01 });
    const pw = tpValidateNumber(document.getElementById('f_pallet_w').value, { label: 'Pallet width', min: 0.01 });
    const ph = tpValidateNumber(document.getElementById('f_pallet_h').value, { label: 'Max stack height', min: 0.01 });
    const cl = tpValidateNumber(document.getElementById('f_carton_l').value, { label: 'Carton length', min: 0.01 });
    const cw = tpValidateNumber(document.getElementById('f_carton_w').value, { label: 'Carton width', min: 0.01 });
    const ch = tpValidateNumber(document.getElementById('f_carton_h').value, { label: 'Carton height', min: 0.01 });

    const perLayerA = Math.floor(pl / cl) * Math.floor(pw / cw);
    const perLayerB = Math.floor(pl / cw) * Math.floor(pw / cl);
    const perLayer = Math.max(perLayerA, perLayerB);
    const layers = Math.floor(ph / ch);
    const total = perLayer * layers;

    tpShowResult(total.toLocaleString() + ' cartons', {
      label: `${perLayer} cartons per layer × ${layers} layers`, raw: total, historyLabel: 'Cartons per Pallet',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
