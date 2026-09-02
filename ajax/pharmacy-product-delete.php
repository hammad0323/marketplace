<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('pharmacy');

$db = db();
$pharmacyId = current_profile_id();
$id = (int) ($_POST['id'] ?? 0);

$stmt = mysqli_prepare($db, "SELECT image FROM doctor_products WHERE id = ? AND pharmacy_id = ? AND seller_type = 'pharmacy' LIMIT 1");
mysqli_stmt_bind_param($stmt, 'ii', $id, $pharmacyId);
mysqli_stmt_execute($stmt);
$product = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$product) {
    json_response(false, [], 'Listing not found.');
}

$stmt = mysqli_prepare($db, "DELETE FROM doctor_products WHERE id = ? AND pharmacy_id = ? AND seller_type = 'pharmacy'");
mysqli_stmt_bind_param($stmt, 'ii', $id, $pharmacyId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($product['image']) {
    $fullPath = UPLOAD_PATH . '/' . $product['image'];
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

json_response(true, [], 'Listing removed.');
