<?php
require __DIR__ . '/../config/config.php';
require_patient_page();

$patientId = current_profile_id();
$filter = $_GET['status'] ?? 'upcoming';
$statusMap = [
    'upcoming' => "a.status IN ('pending','approved')",
    'completed' => "a.status = 'completed'",
    'cancelled' => "a.status IN ('cancelled','rejected','no_show')",
];
$condition = $statusMap[$filter] ?? $statusMap['upcoming'];

$appointments = mysqli_query(db(), "
    SELECT a.*, u.full_name AS doctor_name, u.avatar, d.slug AS doctor_slug,
        (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = d.id) AS spec_name,
        (SELECT COUNT(*) FROM reviews r WHERE r.appointment_id = a.id) AS has_review
    FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users u ON u.id = d.user_id
    WHERE a.patient_id = $patientId AND $condition
    ORDER BY a.appointment_date DESC, a.start_time DESC
");

$pageTitle = 'My Appointments';
$heading = 'My Appointments';
$extraScripts = '<script defer src="/assets/js/calendar-widget.js"></script><script defer src="/assets/js/patient-appointments.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs-row">
    <a href="?status=upcoming" class="tab-btn <?= $filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
    <a href="?status=completed" class="tab-btn <?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
    <a href="?status=cancelled" class="tab-btn <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
</div>

<?php if (mysqli_num_rows($appointments) === 0): ?>
<div class="empty-state card"><i class="ri-calendar-line"></i><h4>No appointments here</h4><p>Nothing to show in this tab yet.</p></div>
<?php else: ?>
<div class="stagger">
<?php while ($a = mysqli_fetch_assoc($appointments)): ?>
<div class="card" style="padding:22px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;" data-reveal data-appt-id="<?= (int)$a['id'] ?>">
    <div style="display:flex;gap:14px;align-items:center;">
        <img src="<?= e(avatar_url($a['avatar'], $a['doctor_name'])) ?>" style="width:52px;height:52px;border-radius:14px;object-fit:cover;">
        <div>
            <strong><?= e($a['doctor_name']) ?></strong>
            <div style="font-size:13px;color:var(--color-text-muted);"><?= e($a['spec_name']) ?></div>
            <div style="font-size:13px;color:var(--color-text-muted);margin-top:4px;">
                <i class="ri-calendar-line"></i> <?= format_date($a['appointment_date']) ?> &nbsp; <i class="ri-time-line"></i> <?= format_time12($a['start_time']) ?>
                &nbsp; <?= $a['consultation_type'] === 'online' ? '<i class="ri-video-chat-line"></i> Online' : '<i class="ri-hospital-line"></i> In-Person' ?>
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;">
        <span class="status-pill status-<?= e($a['status']) ?>"><?= ucfirst($a['status']) ?></span>
        <?php if (in_array($a['status'], ['pending', 'approved'], true)): ?>
        <button class="btn btn-outline btn-sm btn-reschedule" data-doctor-id="<?= (int)$a['doctor_id'] ?>" data-type="<?= e($a['consultation_type']) ?>">Reschedule</button>
        <button class="btn btn-danger btn-sm btn-cancel">Cancel</button>
        <?php elseif ($a['status'] === 'completed' && !$a['has_review']): ?>
        <a href="<?= e(doctor_url($a['doctor_slug'])) ?>#tab-reviews" class="btn btn-outline btn-sm">Leave Review</a>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="reschedule-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:480px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;">
            <h3 style="margin-bottom:18px;">Reschedule Appointment</h3>
            <div id="reschedule-calendar" style="margin-bottom:16px;"></div>
            <div id="reschedule-date-label" style="font-size:13px;color:var(--color-text-muted);margin-bottom:8px;"></div>
            <div class="slot-grid" id="reschedule-slots"></div>
            <button type="button" class="btn btn-primary btn-block" id="confirm-reschedule-btn" style="margin-top:20px;" disabled>Confirm New Time</button>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
