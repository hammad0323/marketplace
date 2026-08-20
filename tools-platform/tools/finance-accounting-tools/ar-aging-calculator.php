<div class="mb-3">
  <label for="f_invoice_date">Invoice Date</label>
  <input type="date" class="form-control" id="f_invoice_date">
</div>
<div class="mb-3">
  <label for="f_terms">Payment Terms (net days)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_terms" value="30">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Check Aging Status</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const dateVal = document.getElementById('f_invoice_date').value;
    if (!dateVal) throw new Error('Please select the invoice date.');
    const terms = tpValidateNumber(document.getElementById('f_terms').value, { label: 'Payment terms', min: 0 });

    const invoiceDate = new Date(dateVal + 'T00:00:00');
    const dueDate = new Date(invoiceDate);
    dueDate.setDate(dueDate.getDate() + terms);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const daysPastDue = Math.floor((today - dueDate) / 86400000);

    let bucket = 'Current (not yet due)';
    if (daysPastDue > 0 && daysPastDue <= 30) bucket = '1–30 days overdue';
    else if (daysPastDue > 30 && daysPastDue <= 60) bucket = '31–60 days overdue';
    else if (daysPastDue > 60 && daysPastDue <= 90) bucket = '61–90 days overdue';
    else if (daysPastDue > 90) bucket = '90+ days overdue';

    tpShowResult(bucket, {
      label: `Due date: ${dueDate.toLocaleDateString()} (${daysPastDue > 0 ? daysPastDue + ' days past due' : Math.abs(daysPastDue) + ' days until due'})`,
      raw: daysPastDue, historyLabel: 'AR Aging',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
