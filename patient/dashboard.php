<?php
require __DIR__ . '/../config/config.php';
require_patient_page();

$user = current_user();
$patientId = current_profile_id();

$stats = mysqli_fetch_assoc(mysqli_query(db(), "
    SELECT
        SUM(status IN ('pending','approved')) AS upcoming,
        SUM(status = 'completed') AS completed,
        SUM(status = 'cancelled') AS cancelled,
        SUM(status = 'completed') * 0 + COALESCE(SUM(CASE WHEN status='completed' THEN fee ELSE 0 END),0) AS total_spent
    FROM appointments WHERE patient_id = $patientId
"));

$upcoming = mysqli_query(db(), "
    SELECT a.*, u.full_name AS doctor_name, u.avatar, s.name AS spec_name
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users u ON u.id = d.user_id
    LEFT JOIN specializations s ON s.id = d.specialization_id
    WHERE a.patient_id = $patientId AND a.status IN ('pending','approved')
    ORDER BY a.appointment_date ASC, a.start_time ASC LIMIT 5
");

$monthly = mysqli_query(db(), "
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') ym, COUNT(*) c
    FROM appointments WHERE patient_id = $patientId AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym
");
$chartLabels = [];
$chartData = [];
while ($m = mysqli_fetch_assoc($monthly)) {
    $chartLabels[] = date('M', strtotime($m['ym'] . '-01'));
    $chartData[] = (int) $m['c'];
}

$pageTitle = 'Dashboard';
$heading = 'My Dashboard';
$extraScripts = '<script src="/assets/js/vendor/chart.umd.js"></script><script>'
    . 'new Chart(document.getElementById("apptChart"), {'
    . 'type: "line",'
    . 'data: { labels: ' . json_encode($chartLabels) . ', datasets: [{'
    . 'label: "Appointments", data: ' . json_encode($chartData) . ','
    . 'borderColor: "#8B5CF6", backgroundColor: "rgba(139,92,246,0.12)", fill: true, tension: 0.4, pointRadius: 4, pointBackgroundColor: "#8B5CF6"'
    . '}] },'
    . 'options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }'
    . '});</script>';
require __DIR__ . '/includes/header.php';
?>
<div class="card-gradient-border" style="margin-bottom:24px;" data-reveal>
    <div class="card-inner" style="padding:28px 32px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
        <div>
            <h2 style="margin-bottom:6px;">Welcome back, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h2>
            <p style="color:var(--color-text-muted);">Here's what's happening with your care.</p>
        </div>
        <a href="/doctors.php" class="btn btn-primary">Book New Appointment <i class="ri-add-line"></i></a>
    </div>
</div>

<div class="grid grid-4 stagger" style="margin-bottom:28px;">
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--gradient-primary);"><i class="ri-calendar-check-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)($stats['upcoming'] ?? 0) ?>">0</b><span>Upcoming</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-success);"><i class="ri-checkbox-circle-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)($stats['completed'] ?? 0) ?>">0</b><span>Completed</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-danger);"><i class="ri-close-circle-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)($stats['cancelled'] ?? 0) ?>">0</b><span>Cancelled</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-warning);"><i class="ri-money-dollar-circle-line"></i></div>
        <div><b><?= format_currency($stats['total_spent'] ?? 0) ?></b><span>Total Spent</span></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:24px;align-items:flex-start;">
    <div class="card table-card" data-reveal>
        <div style="padding:20px 24px;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center;">
            <h4>Upcoming Appointments</h4>
            <a href="/patient/appointments.php" style="font-size:13px;color:var(--color-primary);font-weight:600;">View all</a>
        </div>
        <?php if (mysqli_num_rows($upcoming) === 0): ?>
        <div class="empty-state"><i class="ri-calendar-line"></i><h4>No upcoming appointments</h4><p>Book a consultation with a verified doctor today.</p></div>
        <?php else: ?>
        <table class="data-table">
            <thead><tr><th>Doctor</th><th>Date &amp; Time</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
            <?php while ($a = mysqli_fetch_assoc($upcoming)): ?>
                <tr>
                    <td class="table-user"><img src="<?= e(avatar_url($a['avatar'], $a['doctor_name'])) ?>"><div><?= e($a['doctor_name']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($a['spec_name']) ?></span></div></td>
                    <td><?= format_date($a['appointment_date']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= format_time12($a['start_time']) ?></span></td>
                    <td><?= $a['consultation_type'] === 'online' ? '<i class="ri-video-chat-line"></i> Online' : '<i class="ri-hospital-line"></i> In-Person' ?></td>
                    <td><span class="status-pill status-<?= e($a['status']) ?>"><?= ucfirst($a['status']) ?></span></td>
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
