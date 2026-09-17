<?php
require __DIR__ . '/../config.php';
wh_require_page_access('payments');
$businessId = wh_current_business_id();

$dateFrom = wh_input_get('from');
$dateTo = wh_input_get('to');
$method = wh_input_get('method');

$where = ['p.business_id = ?'];
$types = 'i';
$params = [$businessId];
if ($dateFrom) { $where[] = 'p.payment_date >= ?'; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo) { $where[] = 'p.payment_date <= ?'; $types .= 's'; $params[] = $dateTo; }
if ($method) { $where[] = 'p.payment_method = ?'; $types .= 's'; $params[] = $method; }
$whereSql = implode(' AND ', $where);

$payments = wh_fetch_all(
    "SELECT p.*, b.booking_code, b.final_total, h.name AS hall_name, c.name AS customer_name
     FROM booking_payments p JOIN bookings b ON b.id=p.booking_id JOIN halls h ON h.id=b.hall_id JOIN customers c ON c.id=b.customer_id
     WHERE $whereSql ORDER BY p.payment_date DESC, p.id DESC LIMIT 200",
    $types,
    $params
);
$totalRow = wh_fetch_one("SELECT COALESCE(SUM(p.amount),0) t FROM booking_payments p WHERE $whereSql", $types, $params);

$pendingBookings = wh_fetch_all(
    "SELECT b.*, h.name AS hall_name, c.name AS customer_name FROM bookings b JOIN halls h ON h.id=b.hall_id JOIN customers c ON c.id=b.customer_id
     WHERE b.business_id=? AND b.balance > 0 AND b.booking_status != 'cancelled' ORDER BY b.booking_date ASC LIMIT 50",
    'i',
    [$businessId]
);

$pageTitle = 'Payments';
$activePage = 'payments';
require __DIR__ . '/header.php';
?>
<div class="admin-card">
  <div class="card-head"><h3>Payment History</h3><div class="value" style="font-size:1.1rem;">Total: <?= wh_format_money($totalRow['t'] ?? 0) ?></div></div>
  <form method="get" class="filter-bar">
    <div class="form-group"><label>From</label><input type="date" name="from" value="<?= e($dateFrom) ?>"></div>
    <div class="form-group"><label>To</label><input type="date" name="to" value="<?= e($dateTo) ?>"></div>
    <div class="form-group"><label>Method</label>
      <select name="method"><option value="">All</option>
        <?php foreach (['cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'card' => 'Card', 'other' => 'Other'] as $k => $v): ?>
          <option value="<?= $k ?>" <?= $method === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-light"><i class="fa-solid fa-filter"></i> Filter</button>
  </form>
  <div class="table-scroll">
  <table class="admin-table">
    <thead><tr><th>Date</th><th>Booking</th><th>Customer</th><th>Hall</th><th>Amount</th><th>Method</th><th>Notes</th></tr></thead>
    <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td><?= wh_format_date($p['payment_date']) ?></td>
        <td><a href="<?= e(BASE_URL) ?>/admin/booking-view.php?id=<?= (int) $p['booking_id'] ?>"><?= e($p['booking_code']) ?></a></td>
        <td><?= e($p['customer_name']) ?></td>
        <td><?= e($p['hall_name']) ?></td>
        <td><?= wh_format_money($p['amount']) ?></td>
        <td><?= e(ucwords(str_replace('_', ' ', $p['payment_method']))) ?></td>
        <td><?= e($p['notes']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="7">No payments found.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="admin-card">
  <h3 style="margin-bottom:16px;">Bookings With Pending Balance</h3>
  <table class="admin-table">
    <thead><tr><th>Booking</th><th>Customer</th><th>Hall</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($pendingBookings as $b): ?>
      <tr>
        <td><?= e($b['booking_code']) ?></td>
        <td><?= e($b['customer_name']) ?></td>
        <td><?= e($b['hall_name']) ?></td>
        <td><?= wh_format_date($b['booking_date']) ?></td>
        <td><?= wh_format_money($b['final_total']) ?></td>
        <td><?= wh_format_money($b['paid_amount']) ?></td>
        <td style="color:var(--a-danger);font-weight:600;"><?= wh_format_money($b['balance']) ?></td>
        <td><a href="<?= e(BASE_URL) ?>/admin/booking-view.php?id=<?= (int) $b['id'] ?>" class="btn btn-light btn-sm">View</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$pendingBookings): ?><tr><td colspan="8">No pending balances.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/footer.php'; ?>
