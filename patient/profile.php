<?php
require __DIR__ . '/../config/config.php';
require_patient_page();

$user = current_user();
$patientId = current_profile_id();
$stmt = mysqli_prepare(db(), 'SELECT * FROM patients WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $patientId);
mysqli_stmt_execute($stmt);
$patient = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$pageTitle = 'My Profile';
$heading = 'My Profile';
$extraScripts = '<script src="/assets/js/profile.js"></script>';
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
        <p style="color:var(--color-text-muted);font-size:13.5px;"><?= e($user['email']) ?></p>
        <span class="badge badge-free" style="margin-top:10px;">Patient</span>
    </div>

    <div>
        <div class="card" style="padding:28px;margin-bottom:24px;" data-reveal>
            <h4 style="margin-bottom:20px;">Personal Information</h4>
            <form id="profile-form" novalidate data-endpoint="/ajax/patient-profile-update.php">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="grid grid-2">
                    <div class="form-group" data-field="full_name">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" value="<?= e($user['full_name']) ?>" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email (read-only)</label>
                        <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" data-field="phone">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" value="<?= e($user['phone']) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" name="date_of_birth" value="<?= e($patient['date_of_birth']) ?>">
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select class="form-control" name="gender">
                            <option value="">Select</option>
                            <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= $patient['gender'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Blood Group</label>
                        <input type="text" class="form-control" name="blood_group" value="<?= e($patient['blood_group']) ?>" placeholder="e.g. O+">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="address" value="<?= e($patient['address']) ?>">
                </div>
                <div class="grid grid-3">
                    <div class="form-group"><label class="form-label">City</label><input type="text" class="form-control" name="city" value="<?= e($patient['city']) ?>"></div>
                    <div class="form-group"><label class="form-label">State</label><input type="text" class="form-control" name="state" value="<?= e($patient['state']) ?>"></div>
                    <div class="form-group"><label class="form-label">Country</label><input type="text" class="form-control" name="country" value="<?= e($patient['country']) ?>"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label class="form-label">Emergency Contact Name</label><input type="text" class="form-control" name="emergency_contact_name" value="<?= e($patient['emergency_contact_name']) ?>"></div>
                    <div class="form-group"><label class="form-label">Emergency Contact Phone</label><input type="tel" class="form-control" name="emergency_contact_phone" value="<?= e($patient['emergency_contact_phone']) ?>"></div>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <div class="card" style="padding:28px;" data-reveal>
            <h4 style="margin-bottom:20px;">Change Password</h4>
            <form id="password-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="grid grid-3">
                    <div class="form-group" data-field="current_password">
                        <label class="form-label">Current Password</label>
                        <input type="password" class="form-control" name="current_password" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="new_password">
                        <label class="form-label">New Password</label>
                        <input type="password" class="form-control" name="new_password" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="confirm_password">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                        <div class="form-error"></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-outline">Update Password</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
