<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$db = db();
$stats = [
    'doctors' => mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) c FROM doctors WHERE verification_status='verified'"))['c'],
    'pending_doctors' => mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) c FROM doctors WHERE verification_status='pending'"))['c'],
    'patients' => mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) c FROM patients"))['c'],
    'appointments' => mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) c FROM appointments"))['c'],
    'revenue' => mysqli_fetch_assoc(mysqli_query($db, "SELECT COALESCE(SUM(fee),0) c FROM appointments WHERE status='completed'"))['c'],
    'messages' => mysqli_fetch_assoc(mysqli_query($db, "SELECT COUNT(*) c FROM contact_messages WHERE status='new'"))['c'],
];

$monthly = mysqli_query($db, "
    SELECT DATE_FORMAT(appointment_date, '%Y-%m') ym, COUNT(*) c
    FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY ym ORDER BY ym
");
$chartLabels = []; $chartData = [];
while ($m = mysqli_fetch_assoc($monthly)) {
    $chartLabels[] = date('M', strtotime($m['ym'] . '-01'));
    $chartData[] = (int) $m['c'];
}

$statusBreakdown = mysqli_query($db, "SELECT status, COUNT(*) c FROM appointments GROUP BY status");
$statusLabels = []; $statusData = [];
while ($s = mysqli_fetch_assoc($statusBreakdown)) {
    $statusLabels[] = ucfirst($s['status']);
    $statusData[] = (int) $s['c'];
}

$recentDoctors = mysqli_query($db, "
    SELECT d.id, d.slug, d.verification_status, d.created_at, u.full_name, u.avatar,
        (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = d.id) AS spec_names
    FROM doctors d JOIN users u ON u.id = d.user_id
    ORDER BY d.created_at DESC LIMIT 5
");

$pageTitle = 'Dashboard';
$heading = 'Admin Dashboard';
$extraScripts = '<script src="/assets/js/vendor/chart.umd.js"></script><script>'
    . 'new Chart(document.getElementById("apptChart"), {'
    . 'type: "line",'
    . 'data: { labels: ' . json_encode($chartLabels) . ', datasets: [{ label: "Appointments", data: ' . json_encode($chartData) . ', borderColor: "#0C6B5D", backgroundColor: "rgba(12,107,93,0.12)", fill: true, tension: 0.4 }] },'
    . 'options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }'
    . '});'
    . 'new Chart(document.getElementById("statusChart"), {'
    . 'type: "doughnut",'
    . 'data: { labels: ' . json_encode($statusLabels) . ', datasets: [{ data: ' . json_encode($statusData) . ', backgroundColor: ["#F59E0B","#0C6B5D","#22C55E","#EF4444","#22C3AB","#C4EEE7"] }] },'
    . 'options: { plugins: { legend: { position: "bottom" } } }'
    . '});</script>';
require __DIR__ . '/includes/header.php';
?>
<div class="grid grid-4 stagger" style="margin-bottom:28px;">
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--gradient-primary);"><i class="ri-stethoscope-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)$stats['doctors'] ?>">0</b><span>Verified Doctors</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-warning);"><i class="ri-time-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)$stats['pending_doctors'] ?>">0</b><span>Pending Verification</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:var(--color-success);"><i class="ri-group-line"></i></div>
        <div><b class="counter" data-counter="<?= (int)$stats['patients'] ?>">0</b><span>Patients</span></div>
    </div>
    <div class="card card-hover stat-card" data-reveal>
        <div class="icon" style="background:#F59E0B;"><i class="ri-money-dollar-circle-line"></i></div>
        <div><b><?= format_currency($stats['revenue']) ?></b><span>Total Revenue</span></div>
    </div>
</div>

<div class="split-main-aside-lg">
    <div class="chart-card card" data-reveal>
        <div class="chart-card-head"><h4>Appointments (6 mo)</h4></div>
        <canvas id="apptChart" height="230"></canvas>
    </div>
    <div class="chart-card card" data-reveal="right">
        <div class="chart-card-head"><h4>Status Breakdown</h4></div>
        <canvas id="statusChart" height="230"></canvas>
    </div>
</div>

<div class="card table-card" data-reveal>
    <div style="padding:20px 24px;border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center;">
        <h4>Recent Doctor Applications</h4>
        <a href="/admin/doctors" style="font-size:13px;color:var(--color-primary);font-weight:600;">View all</a>
    </div>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Doctor</th><th>Specialization</th><th>Applied</th><th>Status</th></tr></thead>
        <tbody>
        <?php while ($d = mysqli_fetch_assoc($recentDoctors)): ?>
        <tr>
            <td class="table-user"><img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>"><?= e($d['full_name']) ?></td>
            <td><?= e($d['spec_names']) ?></td>
            <td><?= time_ago($d['created_at']) ?></td>
            <td><span class="status-pill status-<?= e($d['verification_status']) ?>"><?= ucfirst($d['verification_status']) ?></span></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table></div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
