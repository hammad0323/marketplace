<?php
require __DIR__ . '/../config/config.php';
require_doctor_page();

$user = current_user();
$doctorId = current_profile_id();

$stmt = mysqli_prepare(db(), 'SELECT * FROM doctors WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$doctorSpecializations = get_doctor_specializations($doctorId);
$doctorSpecIds = array_map('intval', array_column($doctorSpecializations, 'id'));
$allSpecializations = mysqli_query(db(), 'SELECT id, name FROM specializations WHERE is_active = 1 ORDER BY name');

$stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_privacy_settings WHERE doctor_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$privacy = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_certificates WHERE doctor_id = ? ORDER BY issued_year DESC');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$certificates = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pageTitle = 'My Profile';
$heading = 'My Profile';
$extraScripts = '<script src="/assets/js/profile.js"></script><script src="/assets/js/doctor-profile-extra.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="split-sidebar-left">
    <div class="card" style="padding:28px;text-align:center;" data-reveal>
        <div style="position:relative;width:100px;height:100px;margin:0 auto 16px;">
            <img id="avatar-preview" src="<?= e(avatar_url($user['avatar'], $user['full_name'])) ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;">
            <label for="avatar-input" style="position:absolute;bottom:0;right:0;width:32px;height:32px;border-radius:50%;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md);">
                <i class="ri-camera-line" style="font-size:15px;"></i>
            </label>
            <input type="file" id="avatar-input" accept="image/png,image/jpeg,image/webp" style="display:none;">
        </div>
        <h4><?= e($user['full_name']) ?></h4>
        <p style="color:var(--color-text-muted);font-size:13.5px;"><?= e(specialization_names($doctorSpecializations)) ?></p>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:10px;flex-wrap:wrap;">
            <span class="badge badge-<?= $doctor['verification_status'] === 'verified' ? 'verified' : 'pending' ?>"><?= ucfirst($doctor['verification_status']) ?></span>
            <?php if ($doctor['is_premium']): ?><span class="badge badge-premium">Premium</span><?php endif; ?>
        </div>
    </div>

    <div>
        <div class="tabs-row">
            <button class="tab-btn active" data-tab="info">Profile Info</button>
            <button class="tab-btn" data-tab="privacy">Privacy</button>
            <button class="tab-btn" data-tab="messaging">Messaging</button>
            <button class="tab-btn" data-tab="certs">Certificates</button>
            <button class="tab-btn" data-tab="password">Password</button>
        </div>

        <div class="doc-tab-panel" id="panel-info">
            <div class="card" style="padding:28px;" data-reveal>
                <form id="profile-form" data-endpoint="/ajax/doctor-profile-update.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="grid grid-2">
                        <div class="form-group" data-field="full_name"><label class="form-label">Full Name</label><input type="text" class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required><div class="form-error"></div></div>
                        <div class="form-group"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone" value="<?= e($user['phone']) ?>"></div>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group"><label class="form-label">Qualification</label><input type="text" class="form-control" name="qualification" value="<?= e($doctor['qualification']) ?>"></div>
                        <div class="form-group"><label class="form-label">Years of Experience</label><input type="number" min="0" class="form-control" name="experience_years" value="<?= (int)$doctor['experience_years'] ?>"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Specializations <span style="font-weight:400;color:var(--color-text-muted);">(select one or more)</span></label>
                        <div class="grid grid-3" style="gap:8px;">
                            <?php while ($s = mysqli_fetch_assoc($allSpecializations)): ?>
                            <label class="checkbox-row" style="border:1.5px solid var(--color-border);border-radius:var(--radius-sm);padding:10px 12px;">
                                <input type="checkbox" name="specialization_ids[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $doctorSpecIds, true) ? 'checked' : '' ?>> <?= e($s['name']) ?>
                            </label>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Bio</label><textarea class="form-control" name="bio" rows="4"><?= e($doctor['bio']) ?></textarea></div>
                    <div class="grid grid-2">
                        <div class="form-group"><label class="form-label">Online Consultation Fee ($)</label><input type="number" min="0" step="0.01" class="form-control" name="consultation_fee_online" value="<?= e($doctor['consultation_fee_online']) ?>"></div>
                        <div class="form-group"><label class="form-label">In-Person Consultation Fee ($)</label><input type="number" min="0" step="0.01" class="form-control" name="consultation_fee_physical" value="<?= e($doctor['consultation_fee_physical']) ?>"></div>
                    </div>
                    <label class="checkbox-row" style="margin-bottom:20px;"><input type="checkbox" name="free_consultation" value="1" <?= $doctor['free_consultation'] ? 'checked' : '' ?>> Offer free consultations</label>
                    <div class="divider-fade"></div>
                    <div class="form-group"><label class="form-label">Clinic / Hospital Name</label><input type="text" class="form-control" name="clinic_name" value="<?= e($doctor['clinic_name']) ?>"></div>
                    <div class="form-group"><label class="form-label">Clinic Address</label><input type="text" class="form-control" name="clinic_address" value="<?= e($doctor['clinic_address']) ?>"></div>
                    <div class="grid grid-3">
                        <div class="form-group"><label class="form-label">City</label><input type="text" class="form-control" name="clinic_city" value="<?= e($doctor['clinic_city']) ?>"></div>
                        <div class="form-group"><label class="form-label">State</label><input type="text" class="form-control" name="clinic_state" value="<?= e($doctor['clinic_state']) ?>"></div>
                        <div class="form-group"><label class="form-label">Country</label><input type="text" class="form-control" name="clinic_country" value="<?= e($doctor['clinic_country']) ?>"></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="doc-tab-panel" id="panel-privacy" style="display:none;">
            <div class="card" style="padding:28px;" data-reveal>
                <p style="color:var(--color-text-muted);margin-bottom:20px;">Control which sections of your public profile patients can see.</p>
                <form id="privacy-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <?php
                    $privacyLabels = [
                        'show_certificates' => 'Certificates', 'show_fees' => 'Consultation Fees', 'show_availability' => 'Availability Calendar',
                        'show_clinic_address' => 'Clinic Address', 'show_phone' => 'Phone Number', 'show_email' => 'Email Address',
                        'show_free_consultation' => 'Free Consultation Schedule', 'show_store' => 'Medicine Store', 'show_reviews' => 'Reviews',
                    ];
                    foreach ($privacyLabels as $key => $label): ?>
                    <label class="checkbox-row" style="padding:10px 0;border-bottom:1px solid var(--color-border);">
                        <input type="checkbox" name="<?= $key ?>" value="1" <?= $privacy[$key] ? 'checked' : '' ?>> <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-primary" style="margin-top:20px;">Save Privacy Settings</button>
                </form>
            </div>
        </div>

        <div class="doc-tab-panel" id="panel-messaging" style="display:none;">
            <div class="card" style="padding:28px;" data-reveal>
                <p style="color:var(--color-text-muted);margin-bottom:20px;">Let patients message you directly from your profile. You choose the hours it's available and whether visitors have to sign in first to see it.</p>
                <form id="chat-settings-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <label class="checkbox-row" style="padding:10px 0;border-bottom:1px solid var(--color-border);margin-bottom:16px;">
                        <input type="checkbox" name="chat_enabled" value="1" <?= $doctor['chat_enabled'] ? 'checked' : '' ?>> Enable patient messaging
                    </label>
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Available From</label>
                            <input type="time" class="form-control" name="chat_start_time" value="<?= e($doctor['chat_start_time'] ? substr($doctor['chat_start_time'], 0, 5) : '09:00') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Available Until</label>
                            <input type="time" class="form-control" name="chat_end_time" value="<?= e($doctor['chat_end_time'] ? substr($doctor['chat_end_time'], 0, 5) : '18:00') ?>">
                        </div>
                    </div>
                    <p class="form-hint" style="margin-bottom:16px;">Outside these hours the message button won't appear on your profile. Leave both blank to allow messaging anytime.</p>
                    <label class="checkbox-row" style="margin-bottom:20px;">
                        <input type="checkbox" name="chat_visible_to_guests" value="1" <?= $doctor['chat_visible_to_guests'] ? 'checked' : '' ?>> Show the message button to visitors who aren't logged in (they'll be asked to sign in before sending)
                    </label>
                    <button type="submit" class="btn btn-primary">Save Messaging Settings</button>
                </form>
            </div>
        </div>

        <div class="doc-tab-panel" id="panel-certs" style="display:none;">
            <div class="card" style="padding:28px;margin-bottom:20px;" data-reveal>
                <h4 style="margin-bottom:16px;">Upload Certificate</h4>
                <form id="cert-form" style="display:grid;grid-template-columns:1fr 1fr 100px auto;gap:10px;align-items:end;">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Title</label><input type="text" class="form-control" name="title" placeholder="Leave blank to use file name"></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Issued By</label><input type="text" class="form-control" name="issued_by"></div>
                    <div class="form-group" style="margin-bottom:0;"><label class="form-label">Year</label><input type="number" class="form-control" name="issued_year" min="1950" max="<?= date('Y') ?>"></div>
                    <div class="form-group" style="margin-bottom:0;">
                        <input type="file" name="file" id="cert-file" accept=".jpg,.jpeg,.png,.pdf" multiple required style="display:none;">
                        <label for="cert-file" class="btn btn-outline btn-sm" style="width:100%;text-align:center;">Choose File(s)</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="grid-column:1/-1;">Upload</button>
                </form>
                <p style="font-size:12.5px;color:var(--color-text-muted);margin-top:8px;">You can select multiple files at once. Title/Issued By/Year (if filled) apply to every file in the batch; leave Title blank to use each file's name.</p>
            </div>
            <div class="grid grid-2" id="cert-list">
                <?php foreach ($certificates as $c): ?>
                <div class="card" style="padding:18px;display:flex;justify-content:space-between;align-items:center;" data-cert-id="<?= (int)$c['id'] ?>">
                    <div style="display:flex;gap:12px;align-items:center;">
                        <div style="width:42px;height:42px;border-radius:12px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;"><i class="ri-award-line"></i></div>
                        <div><strong><?= e($c['title']) ?></strong><br><span style="font-size:12.5px;color:var(--color-text-muted);"><?= e($c['issued_by']) ?> · <?= e($c['issued_year']) ?></span></div>
                    </div>
                    <button type="button" class="btn-icon btn-remove-cert" style="width:32px;height:32px;"><i class="ri-delete-bin-line"></i></button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="doc-tab-panel" id="panel-password" style="display:none;">
            <div class="card" style="padding:28px;" data-reveal>
                <form id="password-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="grid grid-3">
                        <div class="form-group" data-field="current_password"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" required><div class="form-error"></div></div>
                        <div class="form-group" data-field="new_password"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" required><div class="form-error"></div></div>
                        <div class="form-group" data-field="confirm_password"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" required><div class="form-error"></div></div>
                    </div>
                    <button type="submit" class="btn btn-outline">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.tabs-row .tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tabs-row .tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.doc-tab-panel').forEach(function (p) { p.style.display = 'none'; });
        btn.classList.add('active');
        document.getElementById('panel-' + btn.getAttribute('data-tab')).style.display = 'block';
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
