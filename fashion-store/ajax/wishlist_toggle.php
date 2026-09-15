<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (!customer_logged_in()) {
    echo json_encode(['success' => false, 'login_required' => true]);
    exit;
}

$productId = (int)($_POST['product_id'] ?? 0);
$customerId = (int)$_SESSION['customer_id'];

$wishlist = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM wishlists WHERE customer_id = $customerId"));
if (!$wishlist) {
    mysqli_query($mysqli, "INSERT INTO wishlists (customer_id) VALUES ($customerId)");
    $wishlistId = mysqli_insert_id($mysqli);
} else {
    $wishlistId = $wishlist['id'];
}

$existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM wishlist_items WHERE wishlist_id = $wishlistId AND product_id = $productId"));
if ($existing) {
    mysqli_query($mysqli, "DELETE FROM wishlist_items WHERE id = " . $existing['id']);
    echo json_encode(['success' => true, 'added' => false]);
} else {
    $stmt = mysqli_prepare($mysqli, "INSERT INTO wishlist_items (wishlist_id, product_id) VALUES (?,?)");
    mysqli_stmt_bind_param($stmt, 'ii', $wishlistId, $productId);
    mysqli_stmt_execute($stmt);
    echo json_encode(['success' => true, 'added' => true]);
}
