<div class="mb-3">
  <label for="f_jwt">JWT Token</label>
  <textarea class="form-control" id="f_jwt" rows="4" style="font-family:monospace;font-size:.78rem;" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."></textarea>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Decode JWT</button>
<div id="tpJwtOutput" class="mt-3"></div>
<script>
function tpBase64UrlDecode(str) {
  str = str.replace(/-/g, '+').replace(/_/g, '/');
  while (str.length % 4) str += '=';
  return decodeURIComponent(atob(str).split('').map((c) => '%' + c.charCodeAt(0).toString(16).padStart(2, '0')).join(''));
}
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const token = document.getElementById('f_jwt').value.trim();
    if (!token) throw new Error('Please paste a JWT token.');
    const parts = token.split('.');
    if (parts.length !== 3) throw new Error('A JWT should have three parts separated by dots (header.payload.signature).');

    const header = JSON.parse(tpBase64UrlDecode(parts[0]));
    const payload = JSON.parse(tpBase64UrlDecode(parts[1]));

    document.getElementById('tpJwtOutput').innerHTML =
      '<p class="fw-semibold mb-1">Header</p><pre class="p-2 rounded" style="background:var(--tp-surface-2);font-size:.8rem;">' + JSON.stringify(header, null, 2) + '</pre>' +
      '<p class="fw-semibold mb-1">Payload</p><pre class="p-2 rounded" style="background:var(--tp-surface-2);font-size:.8rem;">' + JSON.stringify(payload, null, 2) + '</pre>';

    tpShowResult('Decoded ✓', { label: 'This tool does not verify the signature — decoding only', raw: JSON.stringify(payload) });
  } catch (e) { tpShowError('Could not decode this token: ' + e.message); }
});
</script>
