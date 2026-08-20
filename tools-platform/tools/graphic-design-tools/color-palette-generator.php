<div class="mb-3">
  <label for="f_base">Base Color</label>
  <div class="d-flex gap-2">
    <input type="text" class="form-control" id="f_base" value="#7C3AED">
    <input type="color" class="form-control form-control-color" id="f_color_picker" value="#7C3AED">
  </div>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Palette</button>
<div id="tpPaletteOutput" class="d-flex gap-2 mt-3 flex-wrap"></div>
<script>
document.getElementById('f_color_picker').addEventListener('input', function () {
  document.getElementById('f_base').value = this.value;
});
function tpHexToHsl(hex) {
  hex = hex.replace('#', '');
  const r = parseInt(hex.substr(0, 2), 16) / 255, g = parseInt(hex.substr(2, 2), 16) / 255, b = parseInt(hex.substr(4, 2), 16) / 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h, s, l = (max + min) / 2;
  if (max === min) { h = s = 0; } else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      default: h = (r - g) / d + 4;
    }
    h /= 6;
  }
  return [h * 360, s * 100, l * 100];
}
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const hex = document.getElementById('f_base').value.trim();
    if (!/^#[0-9a-fA-F]{6}$/.test(hex)) throw new Error('Enter a valid HEX color like #7C3AED.');
    const [h, s] = tpHexToHsl(hex);

    const lightness = [90, 75, 60, 45, 30, 15];
    const swatches = lightness.map((l) => `hsl(${h.toFixed(0)}, ${s.toFixed(0)}%, ${l}%)`);

    document.getElementById('tpPaletteOutput').innerHTML = swatches.map((c) => `
      <div style="text-align:center;">
        <div style="width:64px;height:64px;border-radius:8px;background:${c};border:1px solid var(--tp-border);"></div>
        <small class="text-muted">${c}</small>
      </div>`).join('');

    tpShowResult('Palette generated ✓', { label: swatches.join(', '), raw: swatches.join(', ') });
  } catch (e) { tpShowError(e.message); }
});
</script>
