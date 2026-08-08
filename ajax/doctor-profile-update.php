<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$db = db();
$userId = (int) $_SESSION['user_id'];
$doctorId = current_profile_id();

$fullName = clean($_POST['full_name'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$qualification = clean($_POST['qualification'] ?? '');
$experienceYears = max(0, (int) ($_POST['experience_years'] ?? 0));
$bio = clean($_POST['bio'] ?? '');
$feeOnline = max(0, (float) ($_POST['consultation_fee_online'] ?? 0));
$feePhysical = max(0, (float) ($_POST['consultation_fee_physical'] ?? 0));
$freeConsultation = !empty($_POST['free_consultation']) ? 1 : 0;
$clinicName = clean($_POST['clinic_name'] ?? '');
$clinicAddress = clean($_POST['clinic_address'] ?? '');
$clinicCity = clean($_POST['clinic_city'] ?? '');
$clinicState = clean($_POST['clinic_state'] ?? '');
$clinicCountry = clean($_POST['clinic_country'] ?? '');
$specializationIds = array_filter(array_map('intval', (array) ($_POST['specialization_ids'] ?? [])));

if ($fullName === '') {
    json_response(false, ['errors' => ['full_name' => 'Full name is required.']], 'Please fix the errors below.');
}
if (!$specializationIds) {
    json_response(false, ['errors' => ['specialization_ids' => 'Select at least one specialization.']], 'Please fix the errors below.');
}

$stmt = mysqli_prepare($db, 'UPDATE users SET full_name = ?, phone = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ssi', $fullName, $phone, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'UPDATE doctors SET qualification = ?, experience_years = ?, bio = ?, consultation_fee_online = ?, consultation_fee_physical = ?, free_consultation = ?, clinic_name = ?, clinic_address = ?, clinic_city = ?, clinic_state = ?, clinic_country = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'sisddisssssi', $qualification, $experienceYears, $bio, $feeOnline, $feePhysical, $freeConsultation, $clinicName, $clinicAddress, $clinicCity, $clinicState, $clinicCountry, $doctorId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

set_doctor_specializations($doctorId, $specializationIds);

$_SESSION['full_name'] = $fullName;
json_response(true, [], 'Profile updated successfully.');
