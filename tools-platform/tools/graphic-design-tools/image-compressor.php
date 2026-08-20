<div class="mb-3">
  <label for="f_file">Choose Image</label>
  <input type="file" class="form-control" id="f_file" accept="image/png,image/jpeg,image/webp">
</div>
<div class="mb-3">
  <label for="f_quality">Quality</label>
  <input type="range" class="form-range" id="f_quality" min="10" max="100" value="70">
  <div class="text-muted small">Quality: <span id="f_quality_val">70</span>%</div>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Compress Image</button>
<div id="tpCompressPreview" class="mt-3"></div>
<script>
document.getElementById('f_quality').addEventListener('input', function () {
  document.getElementById('f_quality_val').textContent = this.value;
});
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  const fileInput = document.getElementById('f_file');
  if (!fileInput.files || !fileInput.files[0]) { tpShowError('Please choose an image file first.'); return; }
  const file = fileInput.files[0];
  const quality = parseInt(document.getElementById('f_quality').value, 10) / 100;
  const originalSizeKB = file.size / 1024;

  const img = new Image();
  const reader = new FileReader();
  reader.onload = function (e) {
    img.onload = function () {
      const canvas = document.createElement('canvas');
      canvas.width = img.width;
      canvas.height = img.height;
      canvas.getContext('2d').drawImage(img, 0, 0);
      const mime = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
      canvas.toBlob(function (blob) {
        const compressedSizeKB = blob.size / 1024;
        const url = URL.createObjectURL(blob);
        const savings = (100 - (compressedSizeKB / originalSizeKB) * 100).toFixed(1);
        document.getElementById('tpCompressPreview').innerHTML =
          `<img src="${url}" style="max-width:100%;max-height:200px;border-radius:8px;" class="mb-2"><br>` +
          `<a href="${url}" download="compressed.jpg" class="btn btn-outline-secondary btn-sm">Download Compressed Image</a>`;
        tpShowResult(compressedSizeKB.toFixed(1) + ' KB', {
          label: `Was ${originalSizeKB.toFixed(1)} KB — ${savings > 0 ? savings + '% smaller' : 'no size reduction at this quality'}`,
          raw: compressedSizeKB.toFixed(1),
        });
      }, mime, quality);
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
});
</script>
