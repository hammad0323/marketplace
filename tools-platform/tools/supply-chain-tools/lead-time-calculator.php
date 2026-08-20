<div class="mb-3">
  <label for="f_order_date">Order Placed Date</label>
  <input type="date" class="form-control" id="f_order_date">
</div>
<div class="mb-3">
  <label for="f_lead_days">Supplier Lead Time (days)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_lead_days" placeholder="e.g. 21">
</div>
<div class="mb-3">
  <label for="f_buffer_days">Safety Buffer (days)</label>
  <input type="number" step="1" min="0" class="form-control" id="f_buffer_days" value="0">
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Expected Arrival</button>
<script>
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const dateVal = document.getElementById('f_order_date').value;
    if (!dateVal) throw new Error('Please select the order date.');
    const leadDays = tpValidateNumber(document.getElementById('f_lead_days').value, { label: 'Lead time', min: 0 });
    const buffer = tpValidateNumber(document.getElementById('f_buffer_days').value || 0, { label: 'Safety buffer', min: 0 });

    const orderDate = new Date(dateVal + 'T00:00:00');
    const arrival = new Date(orderDate);
    arrival.setDate(arrival.getDate() + leadDays + buffer);

    tpShowResult(arrival.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }), {
      label: `${leadDays} day lead time + ${buffer} day buffer`, raw: arrival.toISOString().slice(0, 10), historyLabel: 'Expected Arrival',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
