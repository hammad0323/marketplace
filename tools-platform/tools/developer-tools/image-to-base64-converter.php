<div class="mb-3">
  <label for="f_file">Choose Image</label>
  <input type="file" class="form-control" id="f_file" accept="image/*">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert to Base64</button>
<textarea id="tpBase64Output" class="form-control mt-3" rows="6" readonly style="font-family:monospace;font-size:.75rem;"></textarea>
<div id="tpImagePreview" class="mt-2"></div>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  const fileInput = document.getElementById('f_file');
  if (!fileInput.files || !fileInput.files[0]) { tpShowError('Please choose an image file first.'); return; }
  const file = fileInput.files[0];
  if (file.size > 5 * 1024 * 1024) { tpShowError('Please choose an image under 5MB.'); return; }

  const reader = new FileReader();
  reader.onload = function (e) {
    const dataUrl = e.target.result;
    document.getElementById('tpBase64Output').value = dataUrl;
    document.getElementById('tpImagePreview').innerHTML = `<img src="${dataUrl}" style="max-width:100%;max-height:150px;border-radius:8px;">`;
    tpShowResult((dataUrl.length / 1024).toFixed(1) + ' KB (Base64)', { label: file.name, raw: dataUrl });
  };
  reader.onerror = function () { tpShowError('Could not read this file.'); };
  reader.readAsDataURL(file);
});
</script>
