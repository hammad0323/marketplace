<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('pharmacy');

$db = db();
$userId = (int) $_SESSION['user_id'];
$pharmacyId = current_profile_id();

$storeName = clean($_POST['store_name'] ?? '');
$fullName = clean($_POST['full_name'] ?? '');
$phone = clean($_POST['phone'] ?? '');
$city = clean($_POST['city'] ?? '');
$address = clean($_POST['address'] ?? '');
$registrationNumber = clean($_POST['registration_number'] ?? '');
$licenseAuthority = clean($_POST['license_authority'] ?? '');
$bio = clean($_POST['bio'] ?? '');

if ($storeName === '') {
    json_response(false, ['errors' => ['store_name' => 'Store name is required.']], 'Please fix the errors below.');
}
if ($fullName === '') {
    json_response(false, ['errors' => ['full_name' => 'Owner/contact name is required.']], 'Please fix the errors below.');
}

$stmt = mysqli_prepare($db, 'UPDATE users SET full_name = ?, phone = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ssi', $fullName, $phone, $userId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'UPDATE pharmacies SET store_name = ?, city = ?, address = ?, registration_number = ?, license_authority = ?, bio = ? WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'ssssssi', $storeName, $city, $address, $registrationNumber, $licenseAuthority, $bio, $pharmacyId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$_SESSION['full_name'] = $fullName;
json_response(true, [], 'Profile updated successfully.');
