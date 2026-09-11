<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$filter = $_GET['status'] ?? 'pending';
$statusMap = ['pending' => "d.verification_status = 'pending'", 'verified' => "d.verification_status = 'verified'", 'rejected' => "d.verification_status = 'rejected'", 'all' => '1=1'];
$condition = $statusMap[$filter] ?? $statusMap['pending'];

$doctors = mysqli_query(db(), "
    SELECT d.*, u.full_name, u.avatar, u.email, u.phone, u.status AS user_status,
        (SELECT GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = d.id) AS spec_names
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE $condition ORDER BY d.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$allSpecializations = mysqli_query(db(), 'SELECT id, name FROM specializations WHERE is_active = 1 ORDER BY name')->fetch_all(MYSQLI_ASSOC);
$availByDay = []; // blank defaults for the modal; edit mode fetches + populates via JS

$pageTitle = 'Doctors';
$heading = 'Manage Doctors';
$extraScripts = '<script defer src="/assets/js/admin-doctors.js"></script><script defer src="/assets/js/admin-doctor-form.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:4px;">
    <div class="tabs-row" style="margin-bottom:0;">
        <a href="?status=pending" class="tab-btn <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
        <a href="?status=verified" class="tab-btn <?= $filter === 'verified' ? 'active' : '' ?>">Verified</a>
        <a href="?status=rejected" class="tab-btn <?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
        <a href="?status=all" class="tab-btn <?= $filter === 'all' ? 'active' : '' ?>">All</a>
    </div>
    <button class="btn btn-primary btn-sm" id="add-doctor-btn"><i class="ri-add-line"></i> Add Doctor</button>
</div>

<?php if (!$doctors): ?>
<div class="empty-state card"><i class="ri-stethoscope-line"></i><h4>No doctors here</h4><p>Nothing to show in this tab.</p></div>
<?php else: ?>
<div class="card table-card" data-reveal>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Doctor</th><th>Specialization</th><th>License #</th><th>Applied</th><th>Status</th><th>Account</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($doctors as $d): ?>
        <tr data-doctor-id="<?= (int)$d['id'] ?>" data-meta-title="<?= e($d['meta_title']) ?>" data-meta-description="<?= e($d['meta_description']) ?>">
            <td class="table-user" title="<?= e($d['qualification'] . ' · ' . $d['experience_years'] . ' yrs · ' . excerpt($d['bio'], 160)) ?>">
                <img src="<?= e(avatar_url($d['avatar'], $d['full_name'])) ?>">
                <div><?= e($d['full_name']) ?><br><span style="font-size:12px;color:var(--color-text-muted);"><?= e($d['email']) ?></span></div>
            </td>
            <td><?= e($d['spec_names']) ?></td>
            <td><?= e($d['registration_number']) ?></td>
            <td><?= format_date($d['created_at']) ?></td>
            <td><span class="status-pill status-<?= e($d['verification_status']) ?>"><?= ucfirst($d['verification_status']) ?></span>
                <?php if ($d['is_premium']): ?><span class="badge badge-premium" style="margin-left:4px;">Premium</span><?php endif; ?>
            </td>
            <td><span class="status-pill status-<?= e($d['user_status']) ?>"><?= ucfirst($d['user_status']) ?></span></td>
            <td style="white-space:nowrap;">
                <?php if ($d['verification_status'] === 'pending'): ?>
                <button class="btn btn-primary btn-sm btn-doc-action" data-action="verify">Approve</button>
                <button class="btn btn-danger btn-sm btn-doc-action" data-action="reject">Reject</button>
                <?php else: ?>
                <button class="btn btn-outline btn-sm btn-doc-action" data-action="toggle_premium"><?= $d['is_premium'] ? 'Unset Premium' : 'Make Premium' ?></button>
                <?php if ($d['user_status'] === 'active'): ?>
                <button class="btn btn-danger btn-sm btn-doc-action" data-action="suspend">Suspend</button>
                <?php else: ?>
                <button class="btn btn-primary btn-sm btn-doc-action" data-action="activate">Activate</button>
                <?php endif; ?>
                <?php endif; ?>
                <button class="btn btn-outline btn-sm btn-edit-doctor">Edit</button>
                <a href="<?= e(doctor_url($d['slug'])) ?>" target="_blank" class="btn btn-ghost btn-sm">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="doctor-modal">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:820px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div style="padding:36px;max-height:88vh;overflow-y:auto;">
            <h3 style="margin-bottom:20px;" id="doctor-modal-title">Add Doctor</h3>
            <form id="doctor-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" id="doctor-id" value="0">

                <h4 style="margin-bottom:14px;">Account</h4>
                <div class="grid grid-2">
                    <div class="form-group" data-field="full_name"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" id="doctor-full-name" required><div class="form-error"></div></div>
                    <div class="form-group" data-field="email"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="doctor-email" required><div class="form-error"></div></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone" id="doctor-phone"></div>
                    <div class="form-group" data-field="password">
                        <label class="form-label" id="doctor-password-label">Password</label>
                        <input type="text" class="form-control" name="password" id="doctor-password" placeholder="Leave blank to auto-generate">
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Profile Photo</label><input type="file" class="form-control" name="avatar" accept="image/png,image/jpeg,image/webp"></div>

                <div class="divider-fade"></div>
                <h4 style="margin-bottom:14px;">Professional Details</h4>
                <div class="grid grid-2">
                    <div class="form-group"><label class="form-label">Qualification</label><input type="text" class="form-control" name="qualification" id="doctor-qualification"></div>
                    <div class="form-group"><label class="form-label">License / Registration #</label><input type="text" class="form-control" name="registration_number" id="doctor-registration-number"></div>
                </div>
                <div class="form-group"><label class="form-label">Years of Experience</label><input type="number" min="0" class="form-control" name="experience_years" id="doctor-experience-years" style="max-width:200px;"></div>
                <div class="form-group" data-field="specialization_ids">
                    <label class="form-label">Specializations <span style="font-weight:400;color:var(--color-text-muted);">(select one or more)</span></label>
                    <div class="grid grid-3" id="doctor-specializations" style="gap:8px;">
                        <?php foreach ($allSpecializations as $s): ?>
                        <label class="checkbox-row" style="border:1.5px solid var(--color-border);border-radius:var(--radius-sm);padding:10px 12px;">
                            <input type="checkbox" name="specialization_ids[]" value="<?= (int)$s['id'] ?>"> <?= e($s['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-error"></div>
                </div>
                <div class="form-group"><label class="form-label">Bio</label><textarea class="form-control" name="bio" id="doctor-bio" rows="4"></textarea></div>

                <div class="divider-fade"></div>
                <h4 style="margin-bottom:14px;">Consultation & Clinic</h4>
                <div class="grid grid-2">
                    <div class="form-group"><label class="form-label">Online Consultation Fee</label><input type="number" min="0" step="0.01" class="form-control" name="consultation_fee_online" id="doctor-fee-online"></div>
                    <div class="form-group"><label class="form-label">In-Person Consultation Fee</label><input type="number" min="0" step="0.01" class="form-control" name="consultation_fee_physical" id="doctor-fee-physical"></div>
                </div>
                <label class="checkbox-row" style="margin-bottom:20px;"><input type="checkbox" name="free_consultation" id="doctor-free-consultation" value="1"> Offer free consultations</label>
                <div class="form-group"><label class="form-label">Clinic / Hospital Name</label><input type="text" class="form-control" name="clinic_name" id="doctor-clinic-name"></div>
                <div class="form-group"><label class="form-label">Clinic Address</label><input type="text" class="form-control" name="clinic_address" id="doctor-clinic-address"></div>
                <div class="grid grid-3">
                    <div class="form-group"><label class="form-label">City</label><input type="text" class="form-control" name="clinic_city" id="doctor-clinic-city"></div>
                    <div class="form-group"><label class="form-label">State</label><input type="text" class="form-control" name="clinic_state" id="doctor-clinic-state"></div>
                    <div class="form-group"><label class="form-label">Country</label><input type="text" class="form-control" name="clinic_country" id="doctor-clinic-country"></div>
                </div>

                <div class="divider-fade"></div>
                <h4 style="margin-bottom:4px;">Weekly Availability</h4>
                <p style="color:var(--color-text-muted);font-size:13px;margin-bottom:14px;">Separate hours for online video consultations and the physical clinic/hospital — enable only what applies on each day.</p>
                <?php $availContainerId = 'admin-availability-days'; require __DIR__ . '/../includes/availability-form-fields.php'; ?>

                <div class="divider-fade"></div>
                <h4 style="margin-bottom:4px;">SEO <span style="font-weight:400;color:var(--color-text-muted);font-size:13px;">(optional)</span></h4>
                <p style="color:var(--color-text-muted);font-size:13px;margin-bottom:14px;">Leave blank to auto-generate from name/specialization and bio.</p>
                <div class="form-group"><label class="form-label">Meta Title</label><input type="text" class="form-control" name="meta_title" id="doctor-meta-title" maxlength="200"></div>
                <div class="form-group"><label class="form-label">Meta Description</label><textarea class="form-control" name="meta_description" id="doctor-meta-description" rows="2" maxlength="300"></textarea></div>

                <button type="submit" class="btn btn-primary" id="doctor-form-submit">Save Doctor</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
