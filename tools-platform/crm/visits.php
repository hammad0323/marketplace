<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$visits = tp_query(
    'SELECT v.*, c.name customer_name, c.phone customer_phone FROM crm_visits v
     JOIN crm_customers c ON c.id = v.customer_id
     WHERE v.business_id = ? ORDER BY v.visit_at DESC LIMIT 100',
    'i',
    [$bid]
);

// Customers with a saved location, for the "nearby" finder (real haversine
// distance computed client-side once we know the user's current position).
$geoCustomers = tp_query(
    'SELECT id, name, phone, lat, lng FROM crm_customers WHERE business_id = ? AND lat IS NOT NULL AND lng IS NOT NULL',
    'i',
    [$bid]
);

$crmPageTitle = 'Field Visits';
$crmActive = 'visits';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <button type="button" class="tp-btn tp-btn-sm tp-btn-outline" style="color:var(--tp-indigo);border-color:var(--tp-indigo);" id="findNearbyBtn"><i class="bi bi-geo-alt"></i> Find Nearby Customers</button>
  <a href="<?= tp_url('crm/visit-form.php') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;"><i class="bi bi-plus-lg"></i> Log a Visit</a>
</div>

<div id="nearbyResults" class="admin-card mb-3" style="display:none;">
  <h3 class="h6 fw-bold mb-2">Nearest Customers to You</h3>
  <div id="nearbyList"></div>
</div>

<div class="admin-card">
  <table class="table tp-datatable">
    <thead><tr><th>Date</th><th>Customer</th><th>Notes</th><th>Location</th></tr></thead>
    <tbody>
      <?php foreach ($visits as $v): ?>
      <tr>
        <td><?= date('M j, Y g:ia', strtotime($v['visit_at'])) ?></td>
        <td><a href="<?= tp_url('crm/customer-view.php?id=' . $v['customer_id']) ?>"><?= e($v['customer_name']) ?></a></td>
        <td><?= e($v['notes']) ?></td>
        <td><?= $v['gps_lat'] ? '<a href="https://maps.google.com/maps?q=' . $v['gps_lat'] . ',' . $v['gps_lng'] . '" target="_blank">View on map</a>' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$visits): ?><tr><td colspan="4" class="text-center text-muted py-4">No visits logged yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<script>
const geoCustomers = <?= json_encode($geoCustomers) ?>;

function haversineKm(lat1, lng1, lat2, lng2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

document.getElementById('findNearbyBtn').addEventListener('click', function () {
  if (!geoCustomers.length) {
    document.getElementById('nearbyResults').style.display = 'block';
    document.getElementById('nearbyList').innerHTML = '<p class="text-muted small mb-0">No customers have a saved location yet — GPS check in on a visit first to save one.</p>';
    return;
  }
  if (!navigator.geolocation) { alert('Geolocation is not supported by this browser.'); return; }
  navigator.geolocation.getCurrentPosition((pos) => {
    const { latitude, longitude } = pos.coords;
    const withDistance = geoCustomers.map((c) => ({ ...c, distance: haversineKm(latitude, longitude, parseFloat(c.lat), parseFloat(c.lng)) }))
      .sort((a, b) => a.distance - b.distance);
    document.getElementById('nearbyResults').style.display = 'block';
    document.getElementById('nearbyList').innerHTML = withDistance.map((c) => `
      <div class="d-flex justify-content-between border-bottom py-2 small">
        <span>${c.name}${c.phone ? ' — ' + c.phone : ''}</span>
        <strong>${c.distance.toFixed(1)} km away</strong>
      </div>`).join('');
  }, (err) => { alert('Could not get your location: ' + err.message); });
});
</script>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
