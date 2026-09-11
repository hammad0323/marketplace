<div class="mb-3">
  <label for="f_phone">Phone Number (with country code)</label>
  <input type="text" class="form-control" id="f_phone" placeholder="e.g. 923001234567 or 03001234567">
  <small class="text-muted">Pakistani numbers starting with 0 are auto-converted to +92.</small>
</div>
<div class="mb-3">
  <label for="f_message">Pre-filled Message (optional)</label>
  <textarea class="form-control" id="f_message" rows="3" placeholder="Hi! I'm interested in your product..."></textarea>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate WhatsApp Link</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    let phone = document.getElementById('f_phone').value.trim();
    if (!phone) throw new Error('Please enter a phone number.');
    let digits = phone.replace(/\D+/g, '');
    if (digits.startsWith('0')) digits = '92' + digits.slice(1);
    if (digits.length < 10) throw new Error('That phone number looks too short — include the country code.');

    const message = document.getElementById('f_message').value.trim();
    let link = 'https://wa.me/' + digits;
    if (message) link += '?text=' + encodeURIComponent(message);

    tpShowResult(link, {
      label: 'Your WhatsApp Link — click Copy, then share it anywhere',
      raw: link, historyLabel: 'WhatsApp Link',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
