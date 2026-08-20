<div class="mb-3">
  <label for="f_text">Text or URL</label>
  <input type="text" class="form-control" id="f_text" placeholder="https://example.com">
</div>
<div class="mb-3">
  <label for="f_size">Size (px)</label>
  <select id="f_size" class="form-select">
    <option value="200">200 × 200</option>
    <option value="300" selected>300 × 300</option>
    <option value="500">500 × 500</option>
  </select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate QR Code</button>
<div id="tpQrOutput" class="text-center mt-3"></div>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const text = document.getElementById('f_text').value.trim();
    if (!text) throw new Error('Please enter some text or a URL to encode.');
    const size = parseInt(document.getElementById('f_size').value, 10);

    const container = document.getElementById('tpQrOutput');
    container.innerHTML = '';
    new QRCode(container, { text, width: size, height: size, correctLevel: QRCode.CorrectLevel.M });

    setTimeout(() => {
      const canvas = container.querySelector('canvas');
      if (canvas) {
        const link = document.createElement('a');
        link.textContent = 'Download PNG';
        link.className = 'btn btn-outline-secondary btn-sm mt-2';
        link.href = canvas.toDataURL('image/png');
        link.download = 'qrcode.png';
        container.appendChild(document.createElement('br'));
        container.appendChild(link);
      }
    }, 100);

    tpShowResult('QR Code generated ✓', { label: 'Result', raw: text });
  } catch (e) { tpShowError(e.message); }
});
</script>
