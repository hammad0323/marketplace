<div class="mb-3"><label for="f_name">Customer Name</label><input type="text" class="form-control" id="f_name" placeholder="e.g. Ahmed"></div>
<div class="mb-3"><label for="f_amount">Amount Due</label><input type="number" step="0.01" min="0" class="form-control" id="f_amount" placeholder="e.g. 15000"></div>
<div class="mb-3">
  <label for="f_method">Preferred Payment Method</label>
  <select class="form-select" id="f_method">
    <option value="Easypaisa">Easypaisa</option>
    <option value="JazzCash">JazzCash</option>
    <option value="Bank Transfer">Bank Transfer</option>
    <option value="Cash">Cash</option>
  </select>
</div>
<div class="mb-3"><label for="f_account">Account / Number Details (optional)</label><input type="text" class="form-control" id="f_account" placeholder="e.g. 0300-1234567 or IBAN"></div>
<div class="mb-3"><label for="f_due">Due Date (optional)</label><input type="date" class="form-control" id="f_due"></div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Generate Reminder Message</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const name = document.getElementById('f_name').value.trim() || 'there';
    const amount = tpValidateNumber(document.getElementById('f_amount').value, { label: 'Amount due', min: 0 });
    const method = document.getElementById('f_method').value;
    const account = document.getElementById('f_account').value.trim();
    const due = document.getElementById('f_due').value;

    let msg = `Dear ${name}, this is a friendly reminder that a payment of Rs ${amount.toLocaleString()} is due`;
    if (due) msg += ` by ${new Date(due).toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' })}`;
    msg += `. Please send it via ${method}`;
    if (account) msg += ` (${account})`;
    msg += '. Thank you for your business!';

    tpShowResult(msg, { label: 'Your payment reminder — ready to copy into WhatsApp or SMS', raw: msg, historyLabel: 'Payment Reminder' });
  } catch (e) { tpShowError(e.message); }
});
</script>
