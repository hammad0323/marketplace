<?php
require __DIR__ . '/_init.php';
ajax_require_post();
$c = current_customer();
if (!$c) {
    json_response(['ok' => false, 'message' => 'Please sign in to write a review.'], 401);
}
if (!rate_limit('review:' . $c['id'], 5, 3600)) {
    json_response(['ok' => false, 'message' => 'Too many reviews submitted. Please try later.'], 429);
}
$pid = input_int('product_id');
$rating = input_int('rating');
$body = mb_substr(input('body', '', 'post'), 0, 3000);
if (!db_val("SELECT 1 FROM products WHERE id = ? AND status = 'published'", [$pid])) {
    json_response(['ok' => false, 'message' => 'Product not found.'], 404);
}
if ($rating < 1 || $rating > 5) {
    json_response(['ok' => false, 'message' => 'Please choose a star rating.'], 422);
}
if (mb_strlen($body) < 10) {
    json_response(['ok' => false, 'message' => 'Please write at least a few words about the product.'], 422);
}
if (db_val('SELECT 1 FROM reviews WHERE customer_id = ? AND product_id = ?', [(int) $c['id'], $pid])) {
    json_response(['ok' => false, 'message' => 'You have already reviewed this product.'], 409);
}
$verified = customer_purchased_product((int) $c['id'], $pid);
if (setting_bool('reviews_require_purchase') && !$verified) {
    json_response(['ok' => false, 'message' => 'Only customers who purchased this product can review it.'], 403);
}
$approved = setting_bool('reviews_auto_approve');
db_insert('reviews', [
    'product_id' => $pid, 'customer_id' => (int) $c['id'], 'author_name' => trim($c['first_name'] . ' ' . mb_substr($c['last_name'], 0, 1) . ($c['last_name'] ? '.' : '')),
    'author_city' => nullable(mb_substr(input('city', '', 'post'), 0, 80)), 'rating' => $rating, 'title' => nullable(mb_substr(input('title', '', 'post'), 0, 160)),
    'body' => $body, 'is_verified_purchase' => $verified ? 1 : 0, 'status' => $approved ? 'approved' : 'pending',
]);
if ($approved) {
    product_rating_refresh($pid);
}
json_response(['ok' => true, 'message' => $approved ? 'Thank you! Your review is now live.' : 'Thank you! Your review will appear once it has been approved.']);
