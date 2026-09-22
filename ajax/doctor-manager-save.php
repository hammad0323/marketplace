<?php
/** Doctor creates a staff (manager) login — its own account, scoped to running this doctor's ticket queue only. */
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('doctor');

$doctorId = current_profile_id();
$fullName = clean($_POST['full_name'] ?? '');
$email = strtolower(clean($_POST['email'] ?? ''));
$phone = clean($_POST['phone'] ?? '');

$errors = [];
if ($fullName === '' || mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Please enter a name.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($errors) {
    json_response(false, ['errors' => $errors], 'Please fix the errors below.');
}

$stmt = mysqli_prepare(db(), 'SELECT id FROM users WHERE email = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
    mysqli_stmt_close($stmt);
    json_response(false, ['errors' => ['email' => 'An account with this email already exists.']], 'Please fix the errors below.');
}
mysqli_stmt_close($stmt);

$password = bin2hex(random_bytes(6));
$hash = password_hash($password, PASSWORD_DEFAULT);
$db = db();

mysqli_begin_transaction($db);
try {
    $stmt = mysqli_prepare($db, "INSERT INTO users (role, full_name, email, phone, password_hash, status) VALUES ('manager', ?, ?, ?, ?, 'active')");
    mysqli_stmt_bind_param($stmt, 'ssss', $fullName, $email, $phone, $hash);
    mysqli_stmt_execute($stmt);
    $userId = mysqli_insert_id($db);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db, 'INSERT INTO doctor_managers (doctor_id, user_id) VALUES (?, ?)');
    mysqli_stmt_bind_param($stmt, 'ii', $doctorId, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($db);
} catch (Exception $e) {
    mysqli_rollback($db);
    error_log('doctor-manager-save failed: ' . $e->getMessage());
    json_response(false, [], 'Could not create the staff login. Please try again.');
}

$doctorName = current_user()['full_name'];
send_email($email, $fullName, 'Your staff login for ' . $doctorName . ' — ' . get_setting('site_name', SITE_NAME),
    email_template('Welcome, ' . $fullName . '!',
        '<p>' . e($doctorName) . ' added you as staff to run their ticket queue on ' . e(get_setting('site_name', SITE_NAME)) . '.</p>'
        . '<p><strong>Email:</strong> ' . e($email) . '<br><strong>Password:</strong> ' . e($password) . '</p>'
        . '<p>Log in and you\'ll land straight on the queue — call the next number, or add a walk-in patient who came directly to the clinic.</p>',
        'Log In', APP_URL . '/login'));

log_activity($_SESSION['user_id'], 'doctor', 'add_manager', "Added staff login for $fullName ($email)");

json_response(true, [], 'Staff login created — the password was emailed to ' . $email . '.');
