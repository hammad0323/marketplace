<?php
require __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!current_customer()) {
    echo json_encode(['success' => false, 'message' => 'Please login to write a review.', 'login_required' => true]);
    exit;
}
$customer = current_customer();
$productId = (int)($_POST['product_id'] ?? 0);
$rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$title = clean($_POST['title'] ?? '');
$comment = clean($_POST['comment'] ?? '');

$purchased = db_fetch_one("SELECT oi.id FROM order_items oi JOIN shop_orders so ON so.id=oi.shop_order_id
    JOIN orders o ON o.id=so.order_id WHERE o.customer_id=? AND oi.product_id=? AND so.status='delivered'", 'ii', [$customer['id'], $productId]);
if (!$purchased) {
    echo json_encode(['success' => false, 'message' => 'You can only review products you have purchased and received.']);
    exit;
}
if (!$comment) { echo json_encode(['success' => false, 'message' => 'Please write a comment.']); exit; }

db_insert("INSERT INTO reviews (product_id, customer_id, rating, title, comment, status) VALUES (?,?,?,?,?,'approved')",
    'iiiss', [$productId, $customer['id'], $rating, $title, $comment]);

$agg = db_fetch_one("SELECT AVG(rating) avg_r, COUNT(*) cnt FROM reviews WHERE product_id=? AND status='approved'", 'i', [$productId]);
db_exec("UPDATE products SET rating_avg=?, rating_count=? WHERE id=?", 'dii', [round($agg['avg_r'], 2), $agg['cnt'], $productId]);

echo json_encode(['success' => true, 'message' => 'Thank you for your review!']);
