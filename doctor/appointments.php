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
$extraScripts = '<script defer src="/assets/js/doctor-appointments.js"></script>'
    . '<script defer src="/assets/js/calendar-widget.js"></script>'
    . '<script>window.NA_DOCTOR_ID=' . (int) $doctorId . ';</script>'
    . '<script defer src="/assets/js/doctor-new-appointment.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:4px;">
    <div class="tabs-row" style="margin-bottom:0;">
        <a href="?status=pending" class="tab-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?status=upcoming" class="tab-btn <?= $filter === 'upcoming' ? 'active' : '' ?>">Upcoming</a>
        <a href="?status=completed" class="tab-btn <?= $filter === 'completed' ? 'active' : '' ?>">Completed</a>
        <a href="?status=cancelled" class="tab-btn <?= $filter === 'cancelled' ? 'active' : '' ?>">Cancelled / Rejected</a>
    </div>
    <button class="btn btn-primary btn-sm" id="new-appointment-btn"><i class="ri-add-line"></i> New Appointment</button>
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
        <button class="btn btn-ghost btn-sm btn-send-reminder" title="Email the patient a reminder"><i class="ri-mail-send-line"></i> Remind</button>
        <button class="btn btn-outline btn-sm btn-complete">Mark Completed</button>
        <button class="btn btn-ghost btn-sm btn-noshow">No-Show</button>
        <?php endif; ?>
    </div>
</div>
<?php endwhile; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="new-appointment-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:640px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;max-height:88vh;overflow-y:auto;">
            <h3 style="margin-bottom:20px;">New Appointment</h3>
            <form id="new-appointment-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="patient_id" id="na-patient-id" value="0">

                <div class="form-group" data-field="patient">
                    <label class="form-label">Patient</label>
                    <div id="na-selected-patient" style="display:none;padding:10px 14px;border:1.5px solid var(--color-border);border-radius:var(--radius-sm);margin-bottom:8px;justify-content:space-between;align-items:center;">
                        <span id="na-selected-patient-name"></span>
                        <button type="button" class="btn-icon" id="na-clear-patient" style="width:28px;height:28px;"><i class="ri-close-line"></i></button>
                    </div>
                    <div id="na-patient-search-wrap" style="position:relative;">
                        <input type="text" class="form-control" id="na-patient-search" placeholder="Search patient by name, email, or phone…" autocomplete="off">
                        <div id="na-patient-results" style="position:absolute;left:0;right:0;top:100%;z-index:10;background:var(--color-surface);border:1px solid var(--color-border);border-radius:var(--radius-sm);margin-top:4px;max-height:160px;overflow-y:auto;display:none;box-shadow:var(--shadow-md);"></div>
                    </div>
                    <div class="form-error"></div>
                </div>

                <div id="na-new-patient-fields" style="display:none;">
                    <div class="grid grid-2">
                        <div class="form-group"><label class="form-label">New Patient Name</label><input type="text" class="form-control" name="new_patient_name" id="na-new-name"></div>
                        <div class="form-group"><label class="form-label">Email or Phone</label><input type="text" class="form-control" name="new_patient_contact" id="na-new-contact"></div>
                    </div>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" id="na-toggle-new-patient" style="margin-bottom:20px;">+ Add a new patient instead</button>

                <div class="divider-fade" style="margin-bottom:20px;"></div>

                <div class="na-consult-type-toggle" style="display:flex;gap:8px;margin-bottom:16px;">
                    <button type="button" class="btn btn-primary btn-sm" data-type="online">Online</button>
                    <button type="button" class="btn btn-outline btn-sm" data-type="physical">In-Person</button>
                </div>
                <div id="na-calendar" style="margin-bottom:16px;"></div>
                <div id="na-slot-grid" class="slot-grid" style="margin-bottom:20px;"></div>

                <div class="form-group"><label class="form-label">Reason (optional)</label><textarea class="form-control" name="reason" id="na-reason" rows="2"></textarea></div>

                <button type="submit" class="btn btn-primary" id="na-submit-btn" disabled>Schedule Appointment</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
