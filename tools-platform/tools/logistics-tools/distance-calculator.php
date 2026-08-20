<p class="text-muted small">Calculates straight-line (great-circle) distance between two coordinates.</p>
<div class="row g-2 mb-2">
  <div class="col-6"><label class="small">Point A Latitude</label><input type="number" step="any" class="form-control" id="f_lat1" placeholder="e.g. 40.7128"></div>
  <div class="col-6"><label class="small">Point A Longitude</label><input type="number" step="any" class="form-control" id="f_lon1" placeholder="e.g. -74.0060"></div>
</div>
<div class="row g-2 mb-3">
  <div class="col-6"><label class="small">Point B Latitude</label><input type="number" step="any" class="form-control" id="f_lat2" placeholder="e.g. 34.0522"></div>
  <div class="col-6"><label class="small">Point B Longitude</label><input type="number" step="any" class="form-control" id="f_lon2" placeholder="e.g. -118.2437"></div>
</div>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Calculate Distance</button>
<script>
function tpToRad(deg) { return deg * Math.PI / 180; }
document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const lat1 = tpValidateNumber(document.getElementById('f_lat1').value, { label: 'Point A latitude', min: -90, max: 90 });
    const lon1 = tpValidateNumber(document.getElementById('f_lon1').value, { label: 'Point A longitude', min: -180, max: 180 });
    const lat2 = tpValidateNumber(document.getElementById('f_lat2').value, { label: 'Point B latitude', min: -90, max: 90 });
    const lon2 = tpValidateNumber(document.getElementById('f_lon2').value, { label: 'Point B longitude', min: -180, max: 180 });

    const R = 3958.8; // Earth radius in miles
    const dLat = tpToRad(lat2 - lat1);
    const dLon = tpToRad(lon2 - lon1);
    const a = Math.sin(dLat / 2) ** 2 + Math.cos(tpToRad(lat1)) * Math.cos(tpToRad(lat2)) * Math.sin(dLon / 2) ** 2;
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const miles = R * c;
    const km = miles * 1.60934;

    tpShowResult(miles.toFixed(1) + ' miles', {
      label: `${km.toFixed(1)} km (straight-line / great-circle distance)`, raw: miles.toFixed(2), historyLabel: 'Distance',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
