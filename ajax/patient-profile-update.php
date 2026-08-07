<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('patient');

$db = db();
$userId = (int) $_SESSION['user_id'];
$patientId = current_profile_id();

$fullName = clean($_POST['full_name'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$dob = $_POST['date_of_birth'] ?? null;
$gender = in_array($_POST['gender'] ?? '', ['male', 'female', 'other'], true) ? $_POST['gender'] : null;
$bloodGroup = clean($_POST['blood_group'] ?? '');
$address = clean($_POST['address'] ?? '');
$city = clean($_POST['city'] ?? '');
$state = clean($_POST['state'] ?? '');
$country = clean($_POST['country'] ?? '');
$emergencyName = clean($_POST['emergency_contact_name'] ?? '');
$emergencyPhone = clean($_POST['emergency_contact_phone'] ?? '');

if ($fullName === '') {
    json_response(false, ['errors' => ['full_name' => 'Full name is required.']], 'Please fix the errors below.');
}
if (!$dob) {
    $dob = null;
}

$stmt = mysqli_prepare($db, 'UPDATE users SET full_name = ?, phone = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ssi', $fullName, $phone, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'UPDATE patients SET date_of_birth = ?, gender = ?, blood_group = ?, address = ?, city = ?, state = ?, country = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'sssssssssi', $dob, $gender, $bloodGroup, $address, $city, $state, $country, $emergencyName, $emergencyPhone, $patientId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['full_name'] = $fullName;
json_response(true, [], 'Profile updated successfully.');
