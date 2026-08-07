<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$filter = $_GET['status'] ?? 'pending';
$statusMap = [
    'pending' => "a.status = 'pending'",
    'upcoming' => "a.status = 'approved'",
    'completed' => "a.status = 'completed'",
    'cancelled' => "a.status IN ('cancelled','rejected','no_show')",
];
$condition = $statusMap[$filter] ?? $statusMap['pending'];

$appointments = mysqli_query(db(), "
    SELECT a.*, u.full_name AS patient_name, u.avatar, u.phone, u.email
    FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN users u ON u.id = p.user_id
    WHERE a.doctor_id = $doctorId AND $condition
    ORDER BY a.appointment_date ASC, a.start_time ASC
");

$pageTitle = 'Appointments';
$heading = 'Appointments';
$extraScripts = '<script src="/assets/js/doctor-appointments.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs-row">
    <a href="?status=pending" class="tab-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
    <a href="?status=upcoming" class="tab-btn <?= $filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
    <a href="?status=completed" class="tab-btn <?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
    <a href="?status=cancelled" class="tab-btn <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled / Rejected</a>
</div>

<?php if (mysqli_num_rows($appointments) === 0): ?>
<div class="empty-state card"><i class="ri-calendar-line"></i><h4>Nothing here</h4><p>No appointments in this tab yet.</p></div>
<?php else: ?>
<div class="stagger">
<?php while ($a = mysqli_fetch_assoc($appointments)): ?>
<div class="card" style="padding:22px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;" data-reveal data-appt-id="<?= (int)$a['id'] ?>">
    <div style="display:flex;gap:14px;align-items:center;">
        <img src="<?= e(avatar_url($a['avatar'], $a['patient_name'])) ?>" style="width:52px;height:52px;border-radius:14px;object-fit:cover;">
        <div>
            <strong><?= e($a['patient_name']) ?></strong>
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:2px;">
                <i class="ri-calendar-line"></i> <?= format_date($a['appointment_date']) ?> &nbsp; <i class="ri-time-line"></i> <?= format_time12($a['start_time']) ?>
                &nbsp; <?= $a['consultation_type'] === 'online' ? '<i class="ri-video-chat-line"></i> Online' : '<i class="ri-hospital-line"></i> In-Person' ?>
            </div>
            <?php if ($a['reason']): ?><div style="font-size:13px;color:var(--color-text-muted);margin-top:4px;max-width:420px;"><i class="ri-file-text-line"></i> <?= e($a['reason']) ?></div><?php endif; ?>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <span class="status-pill status-<?= e($a['status']) ?>"><?= ucfirst($a['status']) ?></span>
        <?php if ($a['status'] === 'pending'): ?>
        <button class="btn btn-primary btn-sm btn-approve">Approve</button>
        <button class="btn btn-danger btn-sm btn-reject">Reject</button>
        <?php elseif ($a['status'] === 'approved'): ?>
        <button class="btn btn-outline btn-sm btn-complete">Mark Completed</button>
        <button class="btn btn-ghost btn-sm btn-noshow">No-Show</button>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
