<?php
/**
 * Authentication, session identity, and role-based access control.
 * Included once via config/config.php.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

function is_logged_in()
{
    return !empty($_SESSION['user_id']);
}

function current_user()
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    if (empty($_SESSION['user_id'])) {
        return $user = null;
    }
    $stmt = mysqli_prepare(db(), 'SELECT id, role, full_name, email, phone, avatar, status FROM users WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $user = mysqli_stmt_get_result($stmt)->fetch_assoc() ?: null;
    mysqli_stmt_close($stmt);
    return $user;
}

function current_role()
{
    return $_SESSION['role'] ?? null;
}

/** Doctor/patient profile row id (not the users.id) for the logged-in user. */
function current_profile_id()
{
    $user = current_user();
    if (!$user) {
        return null;
    }
    $table = match ($user['role']) {
        'doctor' => 'doctors',
        'patient' => 'patients',
        'pharmacy' => 'pharmacies',
        default => null,
    };
    if (!$table) {
        return null;
    }
    $stmt = mysqli_prepare(db(), "SELECT id FROM `$table` WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $user['id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['id'] : null;
}

/**
 * Stores the URL the guest was trying to reach so we can bounce them back
 * after a successful login/register (the "smart login" flow).
 */
function set_intended_url($url = null)
{
    $_SESSION['intended_url'] = $url ?? current_url();
}

function get_and_clear_intended_url($fallback = '/')
{
    $url = $_SESSION['intended_url'] ?? $fallback;
    unset($_SESSION['intended_url']);
    // Only ever redirect to a same-site relative path.
    if (!is_string($url) || $url === '' || $url[0] !== '/' || str_starts_with($url, '//')) {
        $url = $fallback;
    }
    return $url;
}

function login_user_row(array $userRow)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $userRow['id'];
    $_SESSION['role'] = $userRow['role'];
    $_SESSION['full_name'] = $userRow['full_name'];
    $stmt = mysqli_prepare(db(), 'UPDATE users SET last_login_at = NOW() WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $userRow['id']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** @return array{0:bool,1:string,2:?array} [success, message, userRow] */
function attempt_login($email, $password)
{
    $email = clean($email);
    if ($email === '' || $password === '') {
        return [false, 'Please enter your email and password.', null];
    }
    if (is_rate_limited($email)) {
        return [false, 'Too many failed attempts. Please try again in a few minutes.', null];
    }

    $stmt = mysqli_prepare(db(), 'SELECT id, role, full_name, email, password_hash, status FROM users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($email, false);
        return [false, 'Invalid email or password.', null];
    }
    if ($user['status'] === 'suspended' || $user['status'] === 'banned') {
        record_login_attempt($email, false);
        return [false, 'Your account has been suspended. Contact support for help.', null];
    }
    if ($user['status'] === 'pending' && $user['role'] === 'doctor') {
        record_login_attempt($email, true);
        return [false, 'Your doctor application is still under review. We\'ll email you once verified.', null];
    }
    if ($user['status'] === 'pending' && $user['role'] === 'pharmacy') {
        record_login_attempt($email, true);
        return [false, 'Your pharmacy registration is still under review. We\'ll email you once verified.', null];
    }

    record_login_attempt($email, true);
    login_user_row($user);
    log_activity($user['id'], $user['role'], 'login', 'User logged in');
    return [true, 'Welcome back, ' . $user['full_name'] . '!', $user];
}

/** @return array{0:bool,1:string} [success, message] */
function register_patient($fullName, $email, $phone, $password)
{
    $db = db();
    $fullName = clean($fullName);
    $email = strtolower(clean($email));
    $phone = clean($phone);

    if ($fullName === '' || $email === '' || strlen($password) < 8) {
        return [false, 'Please fill in all fields. Password must be at least 8 characters.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Please enter a valid email address.'];
    }

    $stmt = mysqli_prepare($db, 'SELECT id FROM users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
        mysqli_stmt_close($stmt);
        return [false, 'An account with this email already exists.'];
    }
    mysqli_stmt_close($stmt);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'INSERT INTO users (role, full_name, email, phone, password_hash, status, email_verified_at) VALUES (\'patient\', ?, ?, ?, ?, \'active\', NOW())');
        mysqli_stmt_bind_param($stmt, 'ssss', $fullName, $email, $phone, $hash);
        mysqli_stmt_execute($stmt);
        $userId = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($db, 'INSERT INTO patients (user_id) VALUES (?)');
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($db);
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log('register_patient failed: ' . $e->getMessage());
        return [false, 'Something went wrong creating your account. Please try again.'];
    }

    $userRow = ['id' => $userId, 'role' => 'patient', 'full_name' => $fullName];
    login_user_row($userRow);
    log_activity($userId, 'patient', 'register', 'Patient account created');

    send_email($email, $fullName, 'Welcome to ' . get_setting('site_name', SITE_NAME) . '!',
        email_template('Welcome, ' . $fullName . '!',
            '<p>Your account is ready. Search verified doctors, compare fees and reviews, and book your first appointment in minutes.</p>',
            'Find a Doctor', APP_URL . '/doctors'));

    return [true, 'Welcome to MediConnect, ' . $fullName . '!'];
}

/** Doctor self-registration — creates a pending account requiring admin verification. */
function register_doctor_application(array $data)
{
    $db = db();
    $fullName = clean($data['full_name'] ?? '');
    $email = strtolower(clean($data['email'] ?? ''));
    $phone = clean($data['phone'] ?? '');
    $password = (string) ($data['password'] ?? '');
    $specializationIds = array_filter(array_map('intval', (array) ($data['specialization_ids'] ?? [])));
    $qualification = clean($data['qualification'] ?? '');
    $registrationNumber = clean($data['registration_number'] ?? '');
    $experienceYears = (int) ($data['experience_years'] ?? 0);
    $bio = clean($data['bio'] ?? '');

    if ($fullName === '' || $email === '' || strlen($password) < 8 || !$specializationIds || $registrationNumber === '') {
        return [false, 'Please fill in all required fields (name, email, password, at least one specialization, license number).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Please enter a valid email address.'];
    }

    $stmt = mysqli_prepare($db, 'SELECT id FROM users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
        mysqli_stmt_close($stmt);
        return [false, 'An account with this email already exists.'];
    }
    mysqli_stmt_close($stmt);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'INSERT INTO users (role, full_name, email, phone, password_hash, status) VALUES (\'doctor\', ?, ?, ?, ?, \'pending\')');
        mysqli_stmt_bind_param($stmt, 'ssss', $fullName, $email, $phone, $hash);
        mysqli_stmt_execute($stmt);
        $userId = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);

        $slug = unique_slug($db, 'doctors', 'dr-' . $fullName);
        $stmt = mysqli_prepare($db, 'INSERT INTO doctors (user_id, slug, qualification, registration_number, experience_years, bio, verification_status) VALUES (?, ?, ?, ?, ?, ?, \'pending\')');
        mysqli_stmt_bind_param($stmt, 'isssis', $userId, $slug, $qualification, $registrationNumber, $experienceYears, $bio);
        mysqli_stmt_execute($stmt);
        $doctorId = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);

        $specStmt = mysqli_prepare($db, 'INSERT INTO doctor_specializations (doctor_id, specialization_id) SELECT ?, id FROM specializations WHERE id = ?');
        foreach ($specializationIds as $specId) {
            mysqli_stmt_bind_param($specStmt, 'ii', $doctorId, $specId);
            mysqli_stmt_execute($specStmt);
        }
        mysqli_stmt_close($specStmt);

        $stmt = mysqli_prepare($db, 'INSERT INTO doctor_privacy_settings (doctor_id) VALUES (?)');
        mysqli_stmt_bind_param($stmt, 'i', $doctorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($db);
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log('register_doctor_application failed: ' . $e->getMessage());
        return [false, 'Something went wrong submitting your application. Please try again.'];
    }

    log_activity($userId, 'doctor', 'apply', 'Doctor application submitted, pending verification');
    notify_admins('doctor_application', 'New doctor application', $fullName . ' applied to join as a doctor and is awaiting verification.', '/admin/doctors?status=pending');

    send_email($email, $fullName, 'Application received — ' . get_setting('site_name', SITE_NAME),
        email_template('Thanks for applying, ' . $fullName . '!',
            '<p>We\'ve received your doctor application and our credentialing team is reviewing your license and details now. '
            . 'You\'ll get an email as soon as your profile is verified and live.</p>'));

    return [true, 'Application submitted! Our team will verify your credentials and email you once approved.'];
}

/** Pharmacy/chemist self-registration — creates a pending account requiring admin verification. */
function register_pharmacy_application(array $data)
{
    $db = db();
    $fullName = clean($data['full_name'] ?? '');
    $email = strtolower(clean($data['email'] ?? ''));
    $phone = clean($data['phone'] ?? '');
    $password = (string) ($data['password'] ?? '');
    $storeName = clean($data['store_name'] ?? '');
    $registrationNumber = clean($data['registration_number'] ?? '');
    $licenseAuthority = clean($data['license_authority'] ?? '');
    $address = clean($data['address'] ?? '');
    $city = clean($data['city'] ?? '');
    $bio = clean($data['bio'] ?? '');

    if ($fullName === '' || $email === '' || strlen($password) < 8 || $storeName === '' || $registrationNumber === '') {
        return [false, 'Please fill in all required fields (owner name, email, password, store name, registration number).'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Please enter a valid email address.'];
    }

    $uploads = handle_multi_upload('certificates', 'certificates', ['jpg', 'jpeg', 'png', 'pdf'], 5 * 1024 * 1024);
    if (!$uploads) {
        return [false, 'Please upload at least one certificate (e.g. your drug license) to support your registration number.'];
    }
    foreach ($uploads as [$ok, $result]) {
        if (!$ok) {
            return [false, 'Certificate upload failed: ' . $result];
        }
    }

    $stmt = mysqli_prepare($db, 'SELECT id FROM users WHERE email = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_get_result($stmt)->fetch_assoc()) {
        mysqli_stmt_close($stmt);
        foreach ($uploads as [, $path]) {
            @unlink(UPLOAD_PATH . '/' . $path);
        }
        return [false, 'An account with this email already exists.'];
    }
    mysqli_stmt_close($stmt);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'INSERT INTO users (role, full_name, email, phone, password_hash, status) VALUES (\'pharmacy\', ?, ?, ?, ?, \'pending\')');
        mysqli_stmt_bind_param($stmt, 'ssss', $fullName, $email, $phone, $hash);
        mysqli_stmt_execute($stmt);
        $userId = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);

        $slug = unique_slug($db, 'pharmacies', $storeName);
        $stmt = mysqli_prepare($db, 'INSERT INTO pharmacies (user_id, slug, store_name, registration_number, license_authority, address, city, bio, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\')');
        mysqli_stmt_bind_param($stmt, 'isssssss', $userId, $slug, $storeName, $registrationNumber, $licenseAuthority, $address, $city, $bio);
        mysqli_stmt_execute($stmt);
        $pharmacyId = mysqli_insert_id($db);
        mysqli_stmt_close($stmt);

        $certStmt = mysqli_prepare($db, 'INSERT INTO pharmacy_certificates (pharmacy_id, title, file_path) VALUES (?, ?, ?)');
        foreach ($uploads as $index => [, $path]) {
            $title = 'Certificate ' . ($index + 1);
            mysqli_stmt_bind_param($certStmt, 'iss', $pharmacyId, $title, $path);
            mysqli_stmt_execute($certStmt);
        }
        mysqli_stmt_close($certStmt);

        mysqli_commit($db);
    } catch (Exception $e) {
        mysqli_rollback($db);
        foreach ($uploads as [, $path]) {
            @unlink(UPLOAD_PATH . '/' . $path);
        }
        error_log('register_pharmacy_application failed: ' . $e->getMessage());
        return [false, 'Something went wrong submitting your registration. Please try again.'];
    }

    log_activity($userId, 'pharmacy', 'apply', 'Pharmacy registration submitted, pending verification');
    notify_admins('pharmacy_application', 'New pharmacy registration', $storeName . ' applied to join as a pharmacy and is awaiting verification.', '/admin/pharmacies?status=pending');

    send_email($email, $fullName, 'Registration received — ' . get_setting('site_name', SITE_NAME),
        email_template('Thanks for registering, ' . $storeName . '!',
            '<p>We\'ve received your pharmacy registration and our team is reviewing your license and certificates now. '
            . 'You\'ll get an email as soon as your store is verified and can start selling.</p>'));

    return [true, 'Registration submitted! Our team will verify your details and email you once approved.'];
}

function logout_user()
{
    if (!empty($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], $_SESSION['role'] ?? null, 'logout', 'User logged out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Access control guards — call at the top of any protected page.
// ---------------------------------------------------------------------------

function require_login_page()
{
    if (!is_logged_in()) {
        set_intended_url();
        redirect('/login');
    }
}

function require_role_page($roles)
{
    require_login_page();
    $roles = (array) $roles;
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}

function require_patient_page()
{
    require_role_page('patient');
}

function require_doctor_page()
{
    require_role_page('doctor');
}

function require_pharmacy_page()
{
    require_role_page('pharmacy');
}

/** For AJAX endpoints: same role check as require_role_page(), but responds with JSON instead of redirecting. */
function require_role_page_or_json($roles)
{
    if (!is_logged_in()) {
        json_response(false, [], 'Please log in to continue.');
    }
    if (!in_array(current_role(), (array) $roles, true)) {
        http_response_code(403);
        json_response(false, [], 'You do not have permission to perform this action.');
    }
}

function require_admin_page()
{
    if (!is_logged_in() || current_role() !== 'admin') {
        set_intended_url();
        redirect('/admin/login');
    }
}
