<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$bookingMode = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT booking_mode FROM doctors WHERE id = ' . (int) $doctorId))['booking_mode'] ?? 'slots';
if ($bookingMode !== 'tickets') {
    redirect('/doctor/dashboard');
}

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-13 days'));
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-13 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$stmt = mysqli_prepare(db(), "
    SELECT ticket_date,
        COUNT(*) AS total,
        SUM(status = 'completed') AS completed,
        SUM(status = 'no_show') AS no_show,
        SUM(status = 'cancelled') AS cancelled,
        SUM(status IN ('waiting', 'serving')) AS still_waiting
    FROM doctor_tickets
    WHERE doctor_id = ? AND ticket_date BETWEEN ? AND ?
    GROUP BY ticket_date ORDER BY ticket_date DESC
");
mysqli_stmt_bind_param($stmt, 'iss', $doctorId, $from, $to);
mysqli_stmt_execute($stmt);
$rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$totals = ['total' => 0, 'completed' => 0, 'no_show' => 0, 'cancelled' => 0, 'still_waiting' => 0];
foreach ($rows as $r) {
    foreach ($totals as $k => $v) {
        $totals[$k] += (int) $r[$k];
    }
}

$pageTitle = 'Ticket Reports';
$heading = 'Ticket Reports';
require __DIR__ . '/includes/header.php';
?>
<form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:24px;">
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
</form>

<div class="grid grid-4" style="gap:16px;margin-bottom:24px;">
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:800;"><?= (int) $totals['total'] ?></div><span style="font-size:12.5px;color:var(--color-text-muted);">Total Tickets</span></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:800;color:var(--color-success, #16A34A);"><?= (int) $totals['completed'] ?></div><span style="font-size:12.5px;color:var(--color-text-muted);">Completed</span></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:800;color:var(--color-danger);"><?= (int) $totals['no_show'] ?></div><span style="font-size:12.5px;color:var(--color-text-muted);">No-shows</span></div>
    <div class="card" style="padding:20px;text-align:center;"><div style="font-size:28px;font-weight:800;color:var(--color-text-muted);"><?= (int) $totals['cancelled'] ?></div><span style="font-size:12.5px;color:var(--color-text-muted);">Cancelled</span></div>
</div>

<?php if (!$rows): ?>
<div class="empty-state card"><i class="ri-bar-chart-2-line"></i><p style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--color-text);">No tickets in this range</p><p>Try a wider date range.</p></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Date</th><th>Total</th><th>Completed</th><th>No-show</th><th>Cancelled</th><th>Still Waiting</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><a href="/doctor/queue?date=<?= e($r['ticket_date']) ?>"><?= format_date($r['ticket_date']) ?></a></td>
            <td><?= (int) $r['total'] ?></td>
            <td><?= (int) $r['completed'] ?></td>
            <td><?= (int) $r['no_show'] ?></td>
            <td><?= (int) $r['cancelled'] ?></td>
            <td><?= (int) $r['still_waiting'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
