<div class="mb-3">
  <label for="f_text">Text or URL</label>
  <textarea class="form-control" id="f_text" rows="5" style="font-family:monospace;font-size:.85rem;" placeholder="https://example.com/search?q=hello world"></textarea>
</div>
<div class="d-flex gap-2 mb-2">
  <button type="button" class="tp-btn-calc" id="tpCalculateBtn" style="width:auto;flex:1;">Encode URL</button>
  <button type="button" class="btn btn-outline-secondary" id="tpDecodeBtn">Decode URL</button>
</div>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const input = document.getElementById('f_text').value;
    if (!input) throw new Error('Please enter some text or a URL to encode.');
    const encoded = encodeURIComponent(input);
    document.getElementById('f_text').value = encoded;
    tpShowResult(encoded.length > 200 ? encoded.slice(0, 200) + '…' : encoded, { label: 'URL Encoded', raw: encoded });
  } catch (e) { tpShowError(e.message); }
});
document.getElementById('tpDecodeBtn').addEventListener('click', function () {
  try {
    const input = document.getElementById('f_text').value;
    if (!input) throw new Error('Please enter an encoded URL to decode.');
    const decoded = decodeURIComponent(input);
    document.getElementById('f_text').value = decoded;
    tpShowResult(decoded.length > 200 ? decoded.slice(0, 200) + '…' : decoded, { label: 'URL Decoded', raw: decoded });
  } catch (e) { tpShowError('This does not look like a validly encoded URL string.'); }
});
</script>
