<div class="mb-3">
  <label for="f_hex">HEX Color</label>
  <div class="d-flex gap-2">
    <input type="text" class="form-control" id="f_hex" placeholder="#6366F1" value="#6366F1">
    <input type="color" class="form-control form-control-color" id="f_color_picker" value="#6366F1">
  </div>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert Color</button>
<script>
document.getElementById('f_color_picker').addEventListener('input', function () {
  document.getElementById('f_hex').value = this.value;
});
function tpHexToRgb(hex) {
  hex = hex.replace('#', '').trim();
  if (hex.length === 3) hex = hex.split('').map((c) => c + c).join('');
  if (!/^[0-9a-fA-F]{6}$/.test(hex)) throw new Error('Enter a valid HEX color like #6366F1.');
  const r = parseInt(hex.substr(0, 2), 16);
  const g = parseInt(hex.substr(2, 2), 16);
  const b = parseInt(hex.substr(4, 2), 16);
  return { r, g, b };
}
function tpRgbToHsl(r, g, b) {
  r /= 255; g /= 255; b /= 255;
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
  return { h: Math.round(h * 360), s: Math.round(s * 100), l: Math.round(l * 100) };
}
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const { r, g, b } = tpHexToRgb(document.getElementById('f_hex').value);
    const { h, s, l } = tpRgbToHsl(r, g, b);
    const k = 1 - Math.max(r / 255, g / 255, b / 255);
    const c = k === 1 ? 0 : (1 - r / 255 - k) / (1 - k);
    const m = k === 1 ? 0 : (1 - g / 255 - k) / (1 - k);
    const y = k === 1 ? 0 : (1 - b / 255 - k) / (1 - k);
    const cmyk = `${Math.round(c*100)}%, ${Math.round(m*100)}%, ${Math.round(y*100)}%, ${Math.round(k*100)}%`;

    tpShowResult(`RGB(${r}, ${g}, ${b})`, {
      label: `HSL(${h}°, ${s}%, ${l}%) — CMYK(${cmyk})`,
      raw: `RGB(${r},${g},${b}) / HSL(${h},${s}%,${l}%) / CMYK(${cmyk})`,
      historyLabel: 'Color Conversion',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
