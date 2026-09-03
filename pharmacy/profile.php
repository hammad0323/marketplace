<?php
require __DIR__ . '/../config/config.php';
require_pharmacy_page();

$user = current_user();
$pharmacyId = current_profile_id();

$stmt = mysqli_prepare(db(), 'SELECT * FROM pharmacies WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $pharmacyId);
mysqli_stmt_execute($stmt);
$pharmacy = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(db(), 'SELECT * FROM pharmacy_certificates WHERE pharmacy_id = ? ORDER BY issued_year DESC, id DESC');
mysqli_stmt_bind_param($stmt, 'i', $pharmacyId);
mysqli_stmt_execute($stmt);
$certificates = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pageTitle = 'My Profile';
$heading = 'My Profile';
$extraScripts = '<script src="/assets/js/profile.js"></script><script>window.CERT_UPLOAD_URL="/ajax/pharmacy-certificate.php";</script><script src="/assets/js/certificate-upload.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="split-sidebar-left">
    <div class="card" style="padding:28px;text-align:center;" data-reveal>
        <div style="position:relative;width:100px;height:100px;margin:0 auto 16px;">
            <img id="avatar-preview" src="<?= e(avatar_url($user['avatar'], $pharmacy['store_name'])) ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;">
            <label for="avatar-input" style="position:absolute;bottom:0;right:0;width:32px;height:32px;border-radius:50%;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:var(--shadow-md);">
                <i class="ri-camera-line" style="font-size:15px;"></i>
            </label>
            <input type="file" id="avatar-input" accept="image/png,image/jpeg,image/webp" style="display:none;">
        </div>
        <h4><?= e($pharmacy['store_name']) ?></h4>
        <p style="color:var(--color-text-muted);font-size:13.5px;"><?= e($pharmacy['city']) ?></p>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:10px;flex-wrap:wrap;">
            <span class="badge badge-<?= $pharmacy['verification_status'] === 'verified' ? 'verified' : 'pending' ?>"><?= ucfirst($pharmacy['verification_status']) ?></span>
        </div>
    </div>

    <div>
        <div class="tabs-row">
            <button class="tab-btn active" data-tab="info">Store Info</button>
            <button class="tab-btn" data-tab="certs">Certificates</button>
            <button class="tab-btn" data-tab="password">Password</button>
        </div>

        <div class="doc-tab-panel" id="panel-info">
            <div class="card" style="padding:28px;" data-reveal>
                <form id="profile-form" data-endpoint="/ajax/pharmacy-profile-update.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="grid grid-2">
                        <div class="form-group" data-field="store_name"><label class="form-label">Store Name</label><input type="text" class="form-control" name="store_name" value="<?= e($pharmacy['store_name']) ?>" required><div class="form-error"></div></div>
                        <div class="form-group" data-field="full_name"><label class="form-label">Owner / Contact Name</label><input type="text" class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required><div class="form-error"></div></div>
                    </div>
                    <div class="grid grid-2">
                        <div class="form-group"><label class="form-label">Phone</label><input type="tel" class="form-control" name="phone" value="<?= e($user['phone']) ?>"></div>
                        <div class="form-group"><label class="form-label">City</label><input type="text" class="form-control" name="city" value="<?= e($pharmacy['city']) ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Address</label><input type="text" class="form-control" name="address" value="<?= e($pharmacy['address']) ?>"></div>
                    <div class="grid grid-2">
                        <div class="form-group"><label class="form-label">Registration Number</label><input type="text" class="form-control" name="registration_number" value="<?= e($pharmacy['registration_number']) ?>"></div>
                        <div class="form-group"><label class="form-label">Issuing Authority</label><input type="text" class="form-control" name="license_authority" value="<?= e($pharmacy['license_authority']) ?>"></div>
                    </div>
                    <div class="form-group"><label class="form-label">About Your Store</label><textarea class="form-control" name="bio" rows="4"><?= e($pharmacy['bio']) ?></textarea></div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
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
                <div class="card" style="padding:18px;display:flex;justify-content:space-between;align-items:center;" data-cert-id="<?= (int) $c['id'] ?>">
                    <div style="display:flex;gap:12px;align-items:center;">
                        <div style="width:42px;height:42px;border-radius:12px;background:var(--gradient-primary);color:#fff;display:flex;align-items:center;justify-content:center;"><i class="ri-award-line"></i></div>
                        <div><a href="/uploads/<?= e($c['file_path']) ?>" target="_blank"><strong><?= e($c['title']) ?></strong></a><br><span style="font-size:12.5px;color:var(--color-text-muted);"><?= e($c['issued_by']) ?> · <?= e($c['issued_year']) ?></span></div>
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
