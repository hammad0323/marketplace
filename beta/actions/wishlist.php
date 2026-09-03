<?php
require __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!current_customer()) {
    echo json_encode(['success' => false, 'message' => 'Please login to use wishlist.', 'login_required' => true]);
    exit;
}
$customer = current_customer();
$productId = (int)($_POST['product_id'] ?? 0);
$do = $_POST['do'] ?? 'toggle';

$existing = db_fetch_one("SELECT id FROM wishlists WHERE customer_id=? AND product_id=?", 'ii', [$customer['id'], $productId]);

if ($do === 'toggle') {
    if ($existing) {
        db_exec("DELETE FROM wishlists WHERE id=?", 'i', [$existing['id']]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from wishlist.']);
    } else {
        db_insert("INSERT INTO wishlists (customer_id, product_id) VALUES (?,?)", 'ii', [$customer['id'], $productId]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to wishlist.']);
    }
    exit;
}
if ($do === 'remove' && $existing) {
    db_exec("DELETE FROM wishlists WHERE id=?", 'i', [$existing['id']]);
    echo json_encode(['success' => true, 'action' => 'removed']);
    exit;
}
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
