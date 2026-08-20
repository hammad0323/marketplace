<div class="mb-3">
  <label for="f_time">Date &amp; Time</label>
  <input type="datetime-local" class="form-control" id="f_time">
</div>
<div class="mb-3">
  <label for="f_from_tz">From Time Zone</label>
  <select id="f_from_tz" class="form-select"></select>
</div>
<div class="mb-3">
  <label for="f_to_tz">To Time Zone</label>
  <select id="f_to_tz" class="form-select"></select>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Convert Time Zone</button>
<script>
const tpZones = [
  'UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
  'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Dubai', 'Asia/Karachi',
  'Asia/Kolkata', 'Asia/Dhaka', 'Asia/Singapore', 'Asia/Shanghai', 'Asia/Tokyo',
  'Australia/Sydney', 'Pacific/Auckland',
];
function tpPopulateZones() {
  ['f_from_tz', 'f_to_tz'].forEach((id) => {
    const sel = document.getElementById(id);
    tpZones.forEach((z) => sel.add(new Option(z, z)));
  });
  document.getElementById('f_to_tz').selectedIndex = 1;
}
tpPopulateZones();

document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const val = document.getElementById('f_time').value;
    if (!val) throw new Error('Please pick a date and time.');
    const fromTz = document.getElementById('f_from_tz').value;
    const toTz = document.getElementById('f_to_tz').value;

    // Interpret the entered local time as if it were in fromTz: find the
    // gap between how "val" reads in UTC vs. in fromTz, then apply that
    // as an offset to get the true UTC instant, which we can then
    // re-render in any target zone.
    const asUTC = new Date(new Date(val).toLocaleString('en-US', { timeZone: 'UTC' }));
    const asFrom = new Date(new Date(val).toLocaleString('en-US', { timeZone: fromTz }));
    const offsetMs = asUTC - asFrom;
    const utcMs = new Date(val).getTime() + offsetMs;

    const converted = new Date(utcMs).toLocaleString('en-US', { timeZone: toTz, dateStyle: 'medium', timeStyle: 'short' });

    tpShowResult(converted, { label: `${fromTz} → ${toTz}`, raw: converted, historyLabel: 'Time Zone Conversion' });
  } catch (e) { tpShowError(e.message); }
});
</script>
