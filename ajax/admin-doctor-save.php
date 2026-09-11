<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$id = (int) ($_POST['id'] ?? 0);

$fullName = clean($_POST['full_name'] ?? '');
$email = strtolower(clean($_POST['email'] ?? ''));
$phone = clean($_POST['phone'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$qualification = clean($_POST['qualification'] ?? '') ?: null;
$registrationNumber = clean($_POST['registration_number'] ?? '') ?: null;
$experienceYears = max(0, (int) ($_POST['experience_years'] ?? 0));
$bio = clean($_POST['bio'] ?? '') ?: null;
$feeOnline = max(0, (float) ($_POST['consultation_fee_online'] ?? 0));
$feePhysical = max(0, (float) ($_POST['consultation_fee_physical'] ?? 0));
$freeConsultation = !empty($_POST['free_consultation']) ? 1 : 0;
$clinicName = clean($_POST['clinic_name'] ?? '') ?: null;
$clinicAddress = clean($_POST['clinic_address'] ?? '') ?: null;
$clinicCity = clean($_POST['clinic_city'] ?? '') ?: null;
$clinicState = clean($_POST['clinic_state'] ?? '') ?: null;
$clinicCountry = clean($_POST['clinic_country'] ?? '') ?: null;
$specializationIds = array_filter(array_map('intval', (array) ($_POST['specialization_ids'] ?? [])));
$metaTitle = mb_substr(clean($_POST['meta_title'] ?? ''), 0, 200) ?: null;
$metaDescription = mb_substr(clean($_POST['meta_description'] ?? ''), 0, 300) ?: null;
$schedule = json_decode($_POST['schedule'] ?? '[]', true);
if (!is_array($schedule)) {
    $schedule = [];
}

$errors = [];
if ($fullName === '' || mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Please enter the doctor\'s full name.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if (!$specializationIds) {
    $errors['specialization_ids'] = 'Select at least one specialization.';
}
if ($id === 0 && $password !== '' && strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters (or leave blank to auto-generate).';
} elseif ($id > 0 && $password !== '' && strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}
if ($errors) {
    json_response(false, ['errors' => $errors], 'Please fix the errors below.');
}

$userId = 0;
if ($id > 0) {
    $stmt = mysqli_prepare($db, 'SELECT user_id FROM doctors WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$existing) {
        json_response(false, [], 'Doctor not found.');
    }
    $userId = (int) $existing['user_id'];
}

$stmt = mysqli_prepare($db, 'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'si', $email, $userId);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, ['errors' => ['email' => 'An account with this email already exists.']], 'Please fix the errors below.');
}
mysqli_stmt_close($stmt);

$avatar = null;
if (!empty($_FILES['avatar']['name'])) {
    [$ok, $result] = handle_upload('avatar', 'avatars', ['jpg', 'jpeg', 'png', 'webp'], 3 * 1024 * 1024);
    if (!$ok) {
        json_response(false, [], $result);
    }
    $avatar = $result;
}

if ($id > 0) {
    if ($avatar !== null) {
        $stmt = mysqli_prepare($db, 'UPDATE users SET full_name = ?, email = ?, phone = ?, avatar = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ssssi', $fullName, $email, $phone, $avatar, $userId);
    } else {
        $stmt = mysqli_prepare($db, 'UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'sssi', $fullName, $email, $phone, $userId);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($db, 'UPDATE users SET password_hash = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $hash, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $stmt = mysqli_prepare($db, 'UPDATE doctors SET qualification=?, registration_number=?, experience_years=?, bio=?, consultation_fee_online=?, consultation_fee_physical=?, free_consultation=?, clinic_name=?, clinic_address=?, clinic_city=?, clinic_state=?, clinic_country=?, meta_title=?, meta_description=? WHERE id=?');
    mysqli_stmt_bind_param(
        $stmt, 'ssisddisssssssi',
        $qualification, $registrationNumber, $experienceYears, $bio, $feeOnline, $feePhysical, $freeConsultation,
        $clinicName, $clinicAddress, $clinicCity, $clinicState, $clinicCountry, $metaTitle, $metaDescription, $id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    set_doctor_specializations($id, $specializationIds);
    save_doctor_availability($id, $schedule);

    log_activity($_SESSION['user_id'], 'admin', 'update_doctor', "Updated doctor #$id: $fullName");
    json_response(true, ['id' => $id], 'Doctor updated.');
}

// --- Create a brand-new doctor account, verified immediately since the admin is vouching for them directly. ---
$generatedPassword = null;
if ($password === '') {
    $generatedPassword = bin2hex(random_bytes(6));
    $password = $generatedPassword;
}
$hash = password_hash($password, PASSWORD_DEFAULT);

mysqli_begin_transaction($db);
try {
    $stmt = mysqli_prepare($db, "INSERT INTO users (role, full_name, email, phone, password_hash, avatar, status) VALUES ('doctor', ?, ?, ?, ?, ?, 'active')");
    mysqli_stmt_bind_param($stmt, 'sssss', $fullName, $email, $phone, $hash, $avatar);
    mysqli_stmt_execute($stmt);
    $userId = mysqli_insert_id($db);
    mysqli_stmt_close($stmt);

    $slug = unique_slug($db, 'doctors', 'dr-' . $fullName);
    $stmt = mysqli_prepare($db, "INSERT INTO doctors (user_id, slug, qualification, registration_number, experience_years, bio, consultation_fee_online, consultation_fee_physical, free_consultation, clinic_name, clinic_address, clinic_city, clinic_state, clinic_country, meta_title, meta_description, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')");
    mysqli_stmt_bind_param(
        $stmt, 'isssisddisssssss',
        $userId, $slug, $qualification, $registrationNumber, $experienceYears, $bio, $feeOnline, $feePhysical, $freeConsultation,
        $clinicName, $clinicAddress, $clinicCity, $clinicState, $clinicCountry, $metaTitle, $metaDescription
    );
    mysqli_stmt_execute($stmt);
    $newId = mysqli_insert_id($db);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db, 'INSERT INTO doctor_privacy_settings (doctor_id) VALUES (?)');
    mysqli_stmt_bind_param($stmt, 'i', $newId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($db);
} catch (Exception $e) {
    mysqli_rollback($db);
    error_log('admin-doctor-save create failed: ' . $e->getMessage());
    json_response(false, [], 'Could not create the doctor account. Please try again.');
}

set_doctor_specializations($newId, $specializationIds);
save_doctor_availability($newId, $schedule);

log_activity($_SESSION['user_id'], 'admin', 'create_doctor', "Created doctor #$newId: $fullName");

send_email($email, $fullName, 'Your doctor account on ' . get_setting('site_name', SITE_NAME),
    email_template('Welcome, Dr. ' . $fullName . '!',
        '<p>An account has been created for you on ' . e(get_setting('site_name', SITE_NAME)) . '.</p>'
        . '<p><strong>Email:</strong> ' . e($email) . '<br><strong>Password:</strong> ' . e($password) . '</p>'
        . '<p>Please log in and change your password from your profile settings.</p>',
        'Log In', APP_URL . '/login'));

json_response(true, ['id' => $newId, 'generated_password' => $generatedPassword], 'Doctor created.' . ($generatedPassword ? " Temporary password: $generatedPassword" : ''));
