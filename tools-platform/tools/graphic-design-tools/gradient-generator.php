<div class="row g-2 mb-3">
  <div class="col-6"><label class="small">Color 1</label><input type="color" class="form-control form-control-color w-100" id="f_color1" value="#7C3AED"></div>
  <div class="col-6"><label class="small">Color 2</label><input type="color" class="form-control form-control-color w-100" id="f_color2" value="#A78BFA"></div>
</div>
<div class="mb-3">
  <label for="f_angle">Angle (degrees)</label>
  <input type="number" class="form-control" id="f_angle" value="135" min="0" max="360">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Gradient</button>
<div id="tpGradientPreview" style="height:100px;border-radius:12px;margin-top:1rem;"></div>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const c1 = document.getElementById('f_color1').value;
    const c2 = document.getElementById('f_color2').value;
    const angle = tpValidateNumber(document.getElementById('f_angle').value, { label: 'Angle', min: 0, max: 360 });

    const css = `linear-gradient(${angle}deg, ${c1} 0%, ${c2} 100%)`;
    document.getElementById('tpGradientPreview').style.background = css;

    const cssCode = `background: ${css};`;
    tpShowResult(cssCode, { label: 'Copy this CSS into your stylesheet', raw: cssCode });
  } catch (e) { tpShowError(e.message); }
});
</script>
