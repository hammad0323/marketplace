<div class="mb-3">
  <label for="f_text">Text</label>
  <textarea class="form-control" id="f_text" rows="6" style="font-family:monospace;font-size:.85rem;" placeholder="Type or paste text here..."></textarea>
</div>
<div class="d-flex gap-2 mb-2">
  <button type="button" class="tp-btn-calc" id="tpCalculateBtn" style="width:auto;flex:1;">Encode to Base64</button>
  <button type="button" class="btn btn-outline-secondary" id="tpDecodeBtn">Decode from Base64</button>
</div>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const input = document.getElementById('f_text').value;
    if (!input) throw new Error('Please enter some text to encode.');
    const encoded = btoa(unescape(encodeURIComponent(input)));
    tpShowResult(encoded.length > 200 ? encoded.slice(0, 200) + '…' : encoded, { label: 'Base64 Encoded', raw: encoded });
    document.getElementById('f_text').value = encoded;
  } catch (e) { tpShowError('Could not encode this text: ' + e.message); }
});
document.getElementById('tpDecodeBtn').addEventListener('click', function () {
  try {
    const input = document.getElementById('f_text').value.trim();
    if (!input) throw new Error('Please enter a Base64 string to decode.');
    const decoded = decodeURIComponent(escape(atob(input)));
    tpShowResult(decoded.length > 200 ? decoded.slice(0, 200) + '…' : decoded, { label: 'Decoded Text', raw: decoded });
    document.getElementById('f_text').value = decoded;
  } catch (e) { tpShowError('This does not look like valid Base64.'); }
});
</script>
