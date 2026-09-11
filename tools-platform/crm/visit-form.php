<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$customers = tp_query('SELECT id, name, phone, lat, lng FROM crm_customers WHERE business_id = ? ORDER BY name', 'i', [$bid]);
$preselect = (int) ($_GET['customer_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $customerId = (int) ($_POST['customer_id'] ?? 0);
    $notes = tp_sanitize_text($_POST['notes'] ?? '', 500);
    $lat = tp_sanitize_number($_POST['gps_lat'] ?? null);
    $lng = tp_sanitize_number($_POST['gps_lng'] ?? null);

    $belongs = tp_query_one('SELECT id FROM crm_customers WHERE id = ? AND business_id = ?', 'ii', [$customerId, $bid]);
    if (!$belongs) {
        $error = 'Please select a valid customer.';
    } else {
        tp_execute(
            'INSERT INTO crm_visits (business_id, customer_id, notes, gps_lat, gps_lng) VALUES (?,?,?,?,?)',
            'iisdd',
            [$bid, $customerId, $notes, $lat, $lng]
        );
        // Save the customer's location too, so future "nearby customers" lookups can use it.
        if ($lat !== null && $lng !== null) {
            tp_execute('UPDATE crm_customers SET lat = ?, lng = ? WHERE id = ? AND business_id = ?', 'ddii', [$lat, $lng, $customerId, $bid]);
        }
        tp_flash_set('success', 'Visit logged.');
        header('Location: ' . tp_url('crm/customer-view.php?id=' . $customerId));
        exit;
    }
}

$crmPageTitle = 'Log a Visit';
$crmActive = 'visits';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="admin-card" style="max-width:560px;">
  <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= tp_csrf_field() ?>
    <div class="mb-3">
      <label class="form-label">Customer *</label>
      <select name="customer_id" class="form-select" required>
        <option value="">— Select —</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $preselect === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Notes</label>
      <textarea name="notes" class="form-control" rows="3" placeholder="What happened during the visit?"></textarea>
    </div>
    <div class="mb-3">
      <button type="button" class="tp-btn tp-btn-outline tp-btn-sm" style="color:var(--tp-indigo);border-color:var(--tp-indigo);" onclick="crmCaptureLocation('gpsLat','gpsLng','gpsStatus')">
        <i class="bi bi-geo-alt"></i> Check In (Capture My Location)
      </button>
      <div id="gpsStatus" class="small text-muted mt-2"></div>
      <input type="hidden" name="gps_lat" id="gpsLat">
      <input type="hidden" name="gps_lng" id="gpsLng">
    </div>
    <div class="d-flex gap-2">
      <button class="tp-btn" style="background:var(--tp-indigo);color:#fff;" type="submit">Log Visit</button>
      <a href="<?= tp_url('crm/visits.php') ?>" class="tp-btn tp-btn-light">Cancel</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
