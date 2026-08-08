<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$status = $_GET['status'] ?? '';
$where = ['1=1'];
$params = [];
$types = '';
if ($status !== '' && in_array($status, ['pending', 'approved', 'completed', 'cancelled', 'rejected', 'no_show'], true)) {
    $where[] = 'a.status = ?';
    $params[] = $status;
    $types .= 's';
}
$whereSql = implode(' AND ', $where);

$countStmt = mysqli_prepare(db(), "SELECT COUNT(*) c FROM appointments a WHERE $whereSql");
if ($types !== '') mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = (int) mysqli_stmt_get_result($countStmt)->fetch_assoc()['c'];
mysqli_stmt_close($countStmt);

$pagination = paginate($total, 15);

$sql = "SELECT a.*, pu.full_name AS patient_name, du.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id JOIN users pu ON pu.id = p.user_id
    JOIN doctors d ON d.id = a.doctor_id JOIN users du ON du.id = d.user_id
    WHERE $whereSql ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare(db(), $sql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
mysqli_stmt_execute($stmt);
$appointments = mysqli_stmt_get_result($stmt);

$pageTitle = 'Appointments';
$heading = 'All Appointments';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs-row">
    <a href="?" class="tab-btn <?= $status === '' ? 'active' : '' ?>">All</a>
    <a href="?status=pending" class="tab-btn <?= $status === 'pending' ? 'active' : '' ?>">Pending</a>
    <a href="?status=approved" class="tab-btn <?= $status === 'approved' ? 'active' : '' ?>">Approved</a>
    <a href="?status=completed" class="tab-btn <?= $status === 'completed' ? 'active' : '' ?>">Completed</a>
    <a href="?status=cancelled" class="tab-btn <?= $status === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
    <a href="?status=rejected" class="tab-btn <?= $status === 'rejected' ? 'active' : '' ?>">Rejected</a>
</div>

<?php if ($total === 0): ?>
<div class="empty-state card"><i class="ri-calendar-line"></i><h4>No appointments found</h4></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <table class="data-table">
        <thead><tr><th>Patient</th><th>Doctor</th><th>Date &amp; Time</th><th>Type</th><th>Fee</th><th>Status</th></tr></thead>
        <tbody>
        <?php while ($a = mysqli_fetch_assoc($appointments)): ?>
        <tr>
            <td><?= e($a['patient_name']) ?></td>
            <td><?= e($a['doctor_name']) ?></td>
            <td><?= format_date($a['appointment_date']) ?> &middot; <?= format_time12($a['start_time']) ?></td>
            <td><?= $a['consultation_type'] === 'online' ? 'Online' : 'In-Person' ?></td>
            <td><?= format_currency($a['fee']) ?></td>
            <td><span class="status-pill status-<?= e($a['status']) ?>"><?= ucfirst($a['status']) ?></span></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?= pagination_links($pagination, '/admin/appointments' . ($status ? '?status=' . e($status) : '')) ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
