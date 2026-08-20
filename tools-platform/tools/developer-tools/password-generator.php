<div class="mb-3">
  <label for="f_length">Password Length</label>
  <input type="number" min="4" max="128" class="form-control" id="f_length" value="16">
</div>
<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" id="f_upper" checked><label class="form-check-label" for="f_upper">Uppercase (A-Z)</label></div>
<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" id="f_lower" checked><label class="form-check-label" for="f_lower">Lowercase (a-z)</label></div>
<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" id="f_numbers" checked><label class="form-check-label" for="f_numbers">Numbers (0-9)</label></div>
<div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="f_symbols"><label class="form-check-label" for="f_symbols">Symbols (!@#$...)</label></div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Password</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const length = tpValidateNumber(document.getElementById('f_length').value, { label: 'Length', min: 4, max: 128 });
    const useUpper = document.getElementById('f_upper').checked;
    const useLower = document.getElementById('f_lower').checked;
    const useNumbers = document.getElementById('f_numbers').checked;
    const useSymbols = document.getElementById('f_symbols').checked;

    let charset = '';
    if (useUpper) charset += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if (useLower) charset += 'abcdefghijklmnopqrstuvwxyz';
    if (useNumbers) charset += '0123456789';
    if (useSymbols) charset += '!@#$%^&*()-_=+[]{}';
    if (!charset) throw new Error('Select at least one character type.');

    const randomValues = new Uint32Array(length);
    crypto.getRandomValues(randomValues);
    let password = '';
    for (let i = 0; i < length; i++) {
      password += charset[randomValues[i] % charset.length];
    }

    tpShowResult(password, { label: 'Generated Password', raw: password });
  } catch (e) { tpShowError(e.message); }
});
</script>
