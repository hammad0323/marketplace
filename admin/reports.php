<?php
require __DIR__ . '/../config.php';
wh_require_page_access('reports');
$businessId = wh_current_business_id();

$dateFrom = wh_input_get('from', date('Y-m-01'));
$dateTo = wh_input_get('to', date('Y-m-t'));
$hallFilter = (int) wh_input_get('hall', 0);
$eventFilter = (int) wh_input_get('event', 0);
$statusFilter = wh_input_get('status');
$paymentFilter = wh_input_get('payment');

$where = ['b.business_id = ?', 'b.booking_date BETWEEN ? AND ?'];
$types = 'iss';
$params = [$businessId, $dateFrom, $dateTo];
if ($hallFilter) { $where[] = 'b.hall_id = ?'; $types .= 'i'; $params[] = $hallFilter; }
if ($eventFilter) { $where[] = 'b.event_type_id = ?'; $types .= 'i'; $params[] = $eventFilter; }
if ($statusFilter) { $where[] = 'b.booking_status = ?'; $types .= 's'; $params[] = $statusFilter; }
if ($paymentFilter) { $where[] = 'b.payment_status = ?'; $types .= 's'; $params[] = $paymentFilter; }
$whereSql = implode(' AND ', $where);

$rows = wh_fetch_all(
    "SELECT b.*, h.name AS hall_name, c.name AS customer_name, ts.name AS slot_name, et.name AS event_type_name
     FROM bookings b JOIN halls h ON h.id=b.hall_id JOIN customers c ON c.id=b.customer_id
     JOIN time_slots ts ON ts.id=b.time_slot_id LEFT JOIN event_types et ON et.id=b.event_type_id
     WHERE $whereSql ORDER BY b.booking_date ASC",
    $types,
    $params
);

if (wh_input_get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bookings-report-' . $dateFrom . '-to-' . $dateTo . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Booking Code', 'Customer', 'Hall', 'Date', 'Slot', 'Event Type', 'Guests', 'Total', 'Paid', 'Balance', 'Booking Status', 'Payment Status']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['booking_code'], $r['customer_name'], $r['hall_name'], $r['booking_date'], $r['slot_name'], $r['event_type_name'], $r['guests'], $r['final_total'], $r['paid_amount'], $r['balance'], $r['booking_status'], $r['payment_status']]);
    }
    fclose($out);
    exit;
}

$summary = ['count' => 0, 'revenue' => 0, 'pending' => 0, 'total_value' => 0];
foreach ($rows as $r) {
    if ($r['booking_status'] === 'cancelled') continue;
    $summary['count']++;
    $summary['revenue'] += (float) $r['paid_amount'];
    $summary['pending'] += (float) $r['balance'];
    $summary['total_value'] += (float) $r['final_total'];
}

$hallWise = [];
foreach ($rows as $r) {
    if ($r['booking_status'] === 'cancelled') continue;
    $hallWise[$r['hall_name']]['count'] = ($hallWise[$r['hall_name']]['count'] ?? 0) + 1;
    $hallWise[$r['hall_name']]['revenue'] = ($hallWise[$r['hall_name']]['revenue'] ?? 0) + (float) $r['paid_amount'];
}
$eventWise = [];
foreach ($rows as $r) {
    if ($r['booking_status'] === 'cancelled') continue;
    $key = $r['event_type_name'] ?? 'Other';
    $eventWise[$key] = ($eventWise[$key] ?? 0) + 1;
}

$halls = wh_get_halls($businessId);
$eventTypes = wh_get_event_types($businessId);

$pageTitle = 'Reports';
$activePage = 'reports';
require __DIR__ . '/header.php';
?>
<div class="admin-card no-print">
  <form method="get" class="filter-bar">
    <div class="form-group"><label>From</label><input type="date" name="from" value="<?= e($dateFrom) ?>"></div>
    <div class="form-group"><label>To</label><input type="date" name="to" value="<?= e($dateTo) ?>"></div>
    <div class="form-group"><label>Hall</label><select name="hall"><option value="">All</option><?php foreach ($halls as $h): ?><option value="<?= (int) $h['id'] ?>" <?= $hallFilter === (int) $h['id'] ? 'selected' : '' ?>><?= e($h['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Event Type</label><select name="event"><option value="">All</option><?php foreach ($eventTypes as $et): ?><option value="<?= (int) $et['id'] ?>" <?= $eventFilter === (int) $et['id'] ? 'selected' : '' ?>><?= e($et['name']) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Status</label><select name="status"><option value="">All</option><?php foreach (['pending', 'confirmed', 'hold', 'completed', 'cancelled'] as $s): ?><option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label>Payment</label><select name="payment"><option value="">All</option><?php foreach (['unpaid', 'partial', 'paid', 'refunded'] as $s): ?><option value="<?= $s ?>" <?= $paymentFilter === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
    <button type="submit" class="btn btn-light"><i class="fa-solid fa-filter"></i> Apply</button>
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-light"><i class="fa-solid fa-file-csv"></i> Export CSV</a>
    <button type="button" class="btn btn-light" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
  </form>
</div>

<div class="stat-grid">
  <div class="admin-card stat-card"><div class="label">Bookings in Range</div><div class="value"><?= $summary['count'] ?></div></div>
  <div class="admin-card stat-card"><div class="label">Total Booking Value</div><div class="value"><?= wh_format_money($summary['total_value']) ?></div></div>
  <div class="admin-card stat-card"><div class="label">Revenue Received</div><div class="value"><?= wh_format_money($summary['revenue']) ?></div></div>
  <div class="admin-card stat-card"><div class="label">Pending Payments</div><div class="value"><?= wh_format_money($summary['pending']) ?></div></div>
</div>

<div class="two-col">
  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Hall-wise Breakdown</h3>
    <table class="simple">
      <thead><tr><th>Hall</th><th>Bookings</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php foreach ($hallWise as $name => $d): ?><tr><td><?= e($name) ?></td><td><?= $d['count'] ?></td><td><?= wh_format_money($d['revenue']) ?></td></tr><?php endforeach; ?>
        <?php if (!$hallWise): ?><tr><td colspan="3">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="admin-card">
    <h3 style="margin-bottom:16px;">Event-Type Breakdown</h3>
    <table class="simple">
      <thead><tr><th>Event Type</th><th>Bookings</th></tr></thead>
      <tbody>
        <?php foreach ($eventWise as $name => $count): ?><tr><td><?= e($name) ?></td><td><?= $count ?></td></tr><?php endforeach; ?>
        <?php if (!$eventWise): ?><tr><td colspan="2">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="admin-card">
  <h3 style="margin-bottom:16px;">Booking Detail</h3>
  <div class="table-scroll">
  <table class="admin-table">
    <thead><tr><th>Code</th><th>Customer</th><th>Hall</th><th>Date</th><th>Slot</th><th>Event</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['booking_code']) ?></td>
        <td><?= e($r['customer_name']) ?></td>
        <td><?= e($r['hall_name']) ?></td>
        <td><?= wh_format_date($r['booking_date']) ?></td>
        <td><?= e($r['slot_name']) ?></td>
        <td><?= e($r['event_type_name'] ?? '—') ?></td>
        <td><?= wh_format_money($r['final_total']) ?></td>
        <td><?= wh_format_money($r['paid_amount']) ?></td>
        <td><?= wh_format_money($r['balance']) ?></td>
        <td><span class="badge badge-<?= e($r['booking_status']) ?>"><?= e(ucfirst($r['booking_status'])) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="10">No bookings in this range.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<style>@media print { .admin-sidebar, .admin-topbar, .no-print { display: none !important; } }</style>
<?php require __DIR__ . '/footer.php'; ?>
