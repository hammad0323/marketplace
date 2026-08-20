<div class="mb-3">
  <label for="f_text">Text or HTML</label>
  <textarea class="form-control" id="f_text" rows="6" style="font-family:monospace;font-size:.85rem;" placeholder="<div class=&quot;example&quot;>Hello & welcome</div>"></textarea>
</div>
<div class="d-flex gap-2 mb-2">
  <button type="button" class="tp-btn-calc" id="tpCalculateBtn" style="width:auto;flex:1;">Encode Entities</button>
  <button type="button" class="btn btn-outline-secondary" id="tpDecodeBtn">Decode Entities</button>
</div>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  const input = document.getElementById('f_text').value;
  if (!input) { tpShowError('Please enter some text or HTML to encode.'); return; }
  const div = document.createElement('div');
  div.textContent = input;
  const encoded = div.innerHTML;
  document.getElementById('f_text').value = encoded;
  tpShowResult(encoded.length > 200 ? encoded.slice(0, 200) + '…' : encoded, { label: 'HTML-Encoded', raw: encoded });
});
document.getElementById('tpDecodeBtn').addEventListener('click', function () {
  const input = document.getElementById('f_text').value;
  if (!input) { tpShowError('Please enter encoded HTML to decode.'); return; }
  const div = document.createElement('div');
  div.innerHTML = input;
  const decoded = div.textContent;
  document.getElementById('f_text').value = decoded;
  tpShowResult(decoded.length > 200 ? decoded.slice(0, 200) + '…' : decoded, { label: 'Decoded Text', raw: decoded });
});
</script>
