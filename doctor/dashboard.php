<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$user = current_user();
$doctorId = current_profile_id();

$stats = mysqli_fetch_assoc(mysqli_query(db(), "
    SELECT
        SUM(status = 'pending') AS pending,
        SUM(status = 'approved' AND appointment_date = CURDATE()) AS today,
        SUM(status = 'completed') AS completed,
        COALESCE(SUM(CASE WHEN status='completed' THEN fee ELSE 0 END),0) AS revenue
    FROM appointments WHERE doctor_id = $doctorId
"));
$totalPatients = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(DISTINCT patient_id) c FROM appointments WHERE doctor_id = $doctorId"))['c'];
$doctorRow = mysqli_fetch_assoc(mysqli_query(db(), "SELECT rating_avg, rating_count, verification_status FROM doctors WHERE id = $doctorId"));

$upcoming = mysqli_query(db(), "
    SELECT a.*, u.full_name AS patient_name, u.avatar
    FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = p.user_id
    WHERE a.doctor_id = $doctorId AND a.status IN ('pending','approved')
    ORDER BY a.appointment_date ASC, a.start_time ASC LIMIT 6
");

$monthly = mysqli_query(db(), "
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') ym, COUNT(*) c
    FROM appointments WHERE doctor_id = $doctorId AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym
");
$chartLabels = []; $chartData = [];
while ($m = mysqli_fetch_assoc($monthly)) {
    $chartLabels[] = date('M', strtotime($m['ym'] . '-01'));
    $chartData[] = (int) $m['c'];
}

$pageTitle = 'Dashboard';
$heading = 'My Dashboard';
$extraScripts = '<script src="/assets/js/vendor/chart.umd.js"></script><script src="/assets/js/doctor-appointments.js"></script><script>'
    . 'new Chart(document.getElementById("apptChart"), {'
    . 'type: "bar",'
    . 'data: { labels: ' . json_encode($chartLabels) . ', datasets: [{ label: "Appointments", data: ' . json_encode($chartData) . ', backgroundColor: "#8B5CF6", borderRadius: 8 }] },'
    . 'options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }'
    . '});</script>';
require __DIR__ . '/includes/header.php';
?>
<div class="card-gradient-border" style="margin-bottom:24px;" data-reveal>
    <div class="card-inner" style="padding:28px 32px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="margin-bottom:6px;">Welcome back, Dr. <?= e(explode(' ', str_replace('Dr. ', '', $user['full_name']))[0]) ?> 👋</h2>
            <p style="color:var(--color-text-muted);">You have <?= (int)($stats['pending'] ?? 0) ?> pending request<?= ($stats['pending'] ?? 0) == 1 ? '' : 's' ?> waiting for review.</p>
        </div>
        <a href="/doctor/appointments?status=pending" class="btn btn-primary">Review Requests <i class="ri-arrow-right-line"></i></a>
    </div>
</div>

<div class="grid grid-4 stagger" style="margin-bottom:28px;">
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-warning);"><i class="ri-time-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)($stats['pending'] ?? 0) ?>">0</b><span>Pending Requests</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--gradient-primary);"><i class="ri-calendar-event-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)($stats['today'] ?? 0) ?>">0</b><span>Today's Appointments</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-success);"><i class="ri-group-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)$totalPatients ?>">0</b><span>Total Patients</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:#F59E0B;"><i class="ri-star-fill"></i></div>
        <div><b><?= number_format($doctorRow['rating_avg'], 1) ?></b><span><?= (int)$doctorRow['rating_count'] ?> Reviews</span></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:flex-start;">
    <div class="card table-card" data-reveal>
        <div style="padding:20px 24px;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center;">
            <h4>Upcoming Appointments</h4>
            <a href="/doctor/appointments" style="font-size:13px;color:var(--color-primary);font-weight:600;">View all</a>
        </div>
        <?php if (mysqli_num_rows($upcoming) === 0): ?>
        <div class="empty-state"><i class="ri-calendar-line"></i><h4>No upcoming appointments</h4><p>New booking requests will appear here.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Patient</th><th>Date &amp; Time</th><th>Type</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php while ($a = mysqli_fetch_assoc($upcoming)): ?>
                <tr data-appt-id="<?= (int)$a['id'] ?>">
                    <td class="table-user"><img src="<?= e(avatar_url($a['avatar'], $a['patient_name'])) ?>"><?= e($a['patient_name']) ?></td>
                    <td><?= format_date($a['appointment_date']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= format_time12($a['start_time']) ?></span></td>
                    <td><?= $a['consultation_type'] === 'online' ? 'Online' : 'In-Person' ?></td>
                    <td><span class="status-pill status-<?= e($a['status']) ?>"><?= ucfirst($a['status']) ?></span></td>
                    <td>
                        <?php if ($a['status'] === 'pending'): ?>
                        <button class="btn btn-primary btn-sm btn-approve">Approve</button>
                        <button class="btn btn-danger btn-sm btn-reject">Reject</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <div class="chart-card card" data-reveal="right">
        <div class="chart-card-head"><h4>Appointments (6 mo)</h4></div>
        <canvas id="apptChart" height="220"></canvas>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
