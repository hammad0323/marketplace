<?php
require __DIR__ . '/../config.php';
wh_require_page_access('bookings');
$businessId = wh_current_business_id();

$search = wh_input_get('q');
$hallFilter = (int) wh_input_get('hall', 0);
$slotFilter = (int) wh_input_get('slot', 0);
$statusFilter = wh_input_get('status');
$paymentFilter = wh_input_get('payment');
$dateFrom = wh_input_get('from');
$dateTo = wh_input_get('to');

$where = ['b.business_id = ?'];
$types = 'i';
$params = [$businessId];

if ($search !== '') {
    $where[] = '(b.booking_code LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
    $types .= 'sss';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($hallFilter) { $where[] = 'b.hall_id = ?'; $types .= 'i'; $params[] = $hallFilter; }
if ($slotFilter) { $where[] = 'b.time_slot_id = ?'; $types .= 'i'; $params[] = $slotFilter; }
if ($statusFilter) { $where[] = 'b.booking_status = ?'; $types .= 's'; $params[] = $statusFilter; }
if ($paymentFilter) { $where[] = 'b.payment_status = ?'; $types .= 's'; $params[] = $paymentFilter; }
if ($dateFrom) { $where[] = 'b.booking_date >= ?'; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo) { $where[] = 'b.booking_date <= ?'; $types .= 's'; $params[] = $dateTo; }

$whereSql = implode(' AND ', $where);
$countRow = wh_fetch_one("SELECT COUNT(*) c FROM bookings b JOIN customers c ON c.id=b.customer_id WHERE $whereSql", $types, $params);
$pg = wh_paginate((int) ($countRow['c'] ?? 0), 20);

$bookings = wh_fetch_all(
    "SELECT b.*, h.name AS hall_name, c.name AS customer_name, c.phone AS customer_phone, ts.name AS slot_name, et.name AS event_type_name
     FROM bookings b JOIN halls h ON h.id=b.hall_id JOIN customers c ON c.id=b.customer_id
     JOIN time_slots ts ON ts.id=b.time_slot_id LEFT JOIN event_types et ON et.id=b.event_type_id
     WHERE $whereSql ORDER BY b.booking_date DESC, b.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $types,
    $params
);

$halls = wh_get_halls($businessId);
$slots = wh_get_time_slots($businessId, false);

$pageTitle = 'Bookings';
$activePage = 'bookings';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head">
    <h3>All Bookings</h3>
    <a href="<?= e(BASE_URL) ?>/admin/booking-form.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add Booking</a>
  </div>
  <form method="get" class="filter-bar">
    <div class="form-group"><label>Search</label><input type="text" name="q" value="<?= e($search) ?>" placeholder="Code, name, phone"></div>
    <div class="form-group"><label>Hall</label><select name="hall"><option value="">All</option><?php foreach ($halls as $h): ?><option value="<?= (int) $h['id'] ?>" <?= $hallFilter === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Time Slot</label><select name="slot"><option value="">All</option><?php foreach ($slots as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $slotFilter === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Status</label>
      <select name="status"><option value="">All</option>
        <?php foreach (['pending', 'confirmed', 'hold', 'completed', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Payment</label>
      <select name="payment"><option value="">All</option>
        <?php foreach (['unpaid', 'partial', 'paid', 'refunded'] as $s): ?><option value="<?= $s ?>" <?= $paymentFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>From</label><input type="date" name="from" value="<?= e($dateFrom) ?>"></div>
    <div class="form-group"><label>To</label><input type="date" name="to" value="<?= e($dateTo) ?>"></div>
    <button type="submit" class="btn btn-light"><i class="fa-solid fa-filter"></i> Filter</button>
    <a href="<?= e(BASE_URL) ?>/admin/bookings.php" class="btn btn-light">Reset</a>
  </form>

  <div class="table-scroll">
  <table class="admin-table">
    <thead><tr><th>Code</th><th>Customer</th><th>Hall</th><th>Date</th><th>Slot</th><th>Event</th><th>Total</th><th>Balance</th><th>Status</th><th>Payment</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($bookings as $b): ?>
      <tr>
        <td><a href="<?= e(BASE_URL) ?>/admin/booking-view.php?id=<?= (int) $b['id'] ?>"><?= e($b['booking_code']) ?></a></td>
        <td><?= e($b['customer_name']) ?><br><span class="hint"><?= e($b['customer_phone']) ?></span></td>
        <td><?= e($b['hall_name']) ?></td>
        <td><?= wh_format_date($b['booking_date']) ?></td>
        <td><?= e($b['slot_name']) ?></td>
        <td><?= e($b['event_type_name'] ?? '—') ?></td>
        <td><?= wh_format_money($b['final_total']) ?></td>
        <td><?= wh_format_money($b['balance']) ?></td>
        <td><span class="badge badge-<?= e($b['booking_status']) ?>"><?= e(ucfirst($b['booking_status'])) ?></span></td>
        <td><span class="badge badge-<?= e($b['payment_status']) ?>"><?= e(ucfirst($b['payment_status'])) ?></span></td>
        <td><a href="<?= e(BASE_URL) ?>/admin/booking-view.php?id=<?= (int) $b['id'] ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-eye"></i></a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$bookings): ?><tr><td colspan="11">No bookings found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($pg['total_pages'] > 1): ?>
  <div style="display:flex;gap:8px;margin-top:16px;justify-content:center;">
    <?php for ($p = 1; $p <= $pg['total_pages']; $p++): $qs = $_GET; $qs['page'] = $p; ?>
      <a href="?<?= http_build_query($qs) ?>" class="btn btn-sm <?= $p === $pg['page'] ? 'btn-primary' : 'btn-light' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
