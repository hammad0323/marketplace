<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$pharmacyId = (int) ($_POST['pharmacy_id'] ?? 0);
$action = $_POST['action'] ?? '';
$note = mb_substr(clean($_POST['note'] ?? ''), 0, 255);

$stmt = mysqli_prepare($db, 'SELECT p.*, u.id AS user_id, u.full_name, u.status AS user_status FROM pharmacies p JOIN users u ON u.id = p.user_id WHERE p.id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $pharmacyId);
mysqli_stmt_execute($stmt);
$pharmacy = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$pharmacy) {
    json_response(false, [], 'Pharmacy not found.');
}

switch ($action) {
    case 'verify':
        mysqli_query($db, "UPDATE pharmacies SET verification_status = 'verified', verification_note = NULL WHERE id = $pharmacyId");
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'active' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $pharmacy['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        notify_user($pharmacy['user_id'], 'account', 'Pharmacy approved', 'Congratulations! Your pharmacy has been verified and can now list products for sale.', '/pharmacy/dashboard');
        $message = 'Pharmacy verified.';
        break;

    case 'reject':
        $stmt = mysqli_prepare($db, "UPDATE pharmacies SET verification_status = 'rejected', verification_note = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $note, $pharmacyId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'suspended' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $pharmacy['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        notify_user($pharmacy['user_id'], 'account', 'Registration not approved', 'Your pharmacy registration was reviewed and could not be approved at this time.' . ($note !== '' ? ' Note from admin: ' . $note : ''), null);
        $message = 'Pharmacy registration rejected.';
        break;

    case 'suspend':
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'suspended' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $pharmacy['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        notify_user($pharmacy['user_id'], 'account', 'Account suspended', 'Your pharmacy account has been suspended by the admin team.' . ($note !== '' ? ' Note: ' . $note : ''), null);
        $message = 'Pharmacy account suspended.';
        break;

    case 'activate':
        $stmt = mysqli_prepare($db, "UPDATE users SET status = 'active' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $pharmacy['user_id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        notify_user($pharmacy['user_id'], 'account', 'Account reactivated', 'Your pharmacy account has been reactivated. You can log in normally now.', '/pharmacy/dashboard');
        $message = 'Pharmacy account reactivated.';
        break;

    default:
        json_response(false, [], 'Invalid action.');
}

log_activity($_SESSION['user_id'], 'admin', 'pharmacy_' . $action, "Pharmacy #$pharmacyId ({$pharmacy['full_name']})");
json_response(true, [], $message);
