<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$doctorId = (int) ($_POST['doctor_id'] ?? 0);
$action = $_POST['action'] ?? '';
$note = mb_substr(clean($_POST['note'] ?? ''), 0, 255);

$stmt = mysqli_prepare($db, 'SELECT d.*, u.id AS user_id, u.full_name, u.status AS user_status FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $doctorId);
mysqli_stmt_execute($stmt);
$doctor = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$doctor) {
    json_response(false, [], 'Doctor not found.');
}

switch ($action) {
    case 'verify':
        mysqli_query($db, "UPDATE doctors SET verification_status = 'verified', verification_note = NULL WHERE id = $doctorId");
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'active' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $doctor['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        notify_user($doctor['user_id'], 'account', 'Application approved', 'Congratulations! Your doctor profile has been verified and is now live.', '/doctor/dashboard.php');
        $message = 'Doctor verified.';
        break;

    case 'reject':
        $stmt = mysqli_prepare($db, "UPDATE doctors SET verification_status = 'rejected', verification_note = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $note, $doctorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'suspended' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $doctor['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = 'Doctor application rejected.';
        break;

    case 'toggle_premium':
        mysqli_query($db, 'UPDATE doctors SET is_premium = 1 - is_premium WHERE id = ' . $doctorId);
        $message = 'Premium status updated.';
        break;

    case 'suspend':
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'suspended' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $doctor['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = 'Doctor account suspended.';
        break;

    case 'activate':
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'active' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $doctor['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = 'Doctor account reactivated.';
        break;

    default:
        json_response(false, [], 'Invalid action.');
}

log_activity($_SESSION['user_id'], 'admin', 'doctor_' . $action, "Doctor #$doctorId ({$doctor['full_name']})");
json_response(true, [], $message);
