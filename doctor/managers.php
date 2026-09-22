<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$doctorId = current_profile_id();
$bookingMode = mysqli_fetch_assoc(mysqli_query(db(), 'SELECT booking_mode FROM doctors WHERE id = ' . (int) $doctorId))['booking_mode'] ?? 'slots';
if ($bookingMode !== 'tickets') {
    redirect('/doctor/dashboard');
}

$managers = mysqli_query(db(), '
    SELECT m.id, m.created_at, u.full_name, u.email, u.phone, u.status
    FROM doctor_managers m JOIN users u ON u.id = m.user_id
    WHERE m.doctor_id = ' . (int) $doctorId . ' ORDER BY m.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Staff Access';
$heading = 'Staff Access';
$extraScripts = '<script defer src="' . asset_url('/assets/js/doctor-managers.js') . '"></script>';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <p style="color:var(--color-text-muted);margin:0;max-width:560px;">Give your front-desk staff their own login to run the ticket queue — add walk-ins and call the next number — without giving them access to anything else in your account.</p>
    <button class="btn btn-primary btn-sm" id="add-manager-btn"><i class="ri-add-line"></i> Add Staff Login</button>
</div>

<?php if (!$managers): ?>
<div class="empty-state card"><i class="ri-team-line"></i><p style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--color-text);">No staff accounts yet</p><p>Add one so your front desk can run the queue while you're seeing patients.</p></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Added</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($managers as $m): ?>
        <tr data-manager-id="<?= (int) $m['id'] ?>">
            <td><?= e($m['full_name']) ?></td>
            <td><?= e($m['email']) ?></td>
            <td><?= e($m['phone'] ?: '—') ?></td>
            <td><?= format_date($m['created_at']) ?></td>
            <td><span class="status-pill status-<?= e($m['status']) ?>"><?= ucfirst($m['status']) ?></span></td>
            <td>
                <button type="button" class="btn btn-outline btn-sm btn-reset-manager-password">Reset Password</button>
                <button type="button" class="btn btn-danger btn-sm btn-remove-manager">Remove</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="manager-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:420px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:32px;">
            <h3 style="margin-bottom:18px;">Add Staff Login</h3>
            <form id="manager-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="form-group" data-field="full_name">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="full_name" required>
                    <div class="form-error"></div>
                </div>
                <div class="form-group" data-field="email">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                    <div class="form-error"></div>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="tel" class="form-control" name="phone">
                </div>
                <p style="color:var(--color-text-muted);font-size:13px;margin-bottom:14px;">A password is generated automatically and emailed to them, along with the login link.</p>
                <button type="submit" class="btn btn-primary btn-block">Create Login</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
