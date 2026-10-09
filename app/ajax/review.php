<?php
if (!is_post() || !setting_bool('reviews_enabled', true)) {
    json_response(['ok' => false, 'message' => 'Reviews are not available.'], 405);
}
require_csrf();
if (input('website') !== '') {
    json_response(['ok' => true, 'message' => 'Thank you! Your review will appear once approved.']);
}
$pid = input_int('product_id');
$rating = input_int('rating');
$name = input('name');
$email = mb_strtolower(input('email'));
$title = input('title');
$body = input('body');
$errors = [];
if (!product_by_id($pid)) { $errors[] = 'Product not found.'; }
if ($rating < 1 || $rating > 5) { $errors[] = 'Please choose a star rating.'; }
if (mb_strlen($name) < 2 || mb_strlen($name) > 120) { $errors[] = 'Please enter your name.'; }
if (!valid_email($email)) { $errors[] = 'Please enter a valid email.'; }
if (mb_strlen($title) > 150) { $errors[] = 'Title is too long.'; }
if (mb_strlen($body) < 10 || mb_strlen($body) > 3000) { $errors[] = 'Your review should be 10–3000 characters.'; }
if ($errors) {
    json_response(['ok' => false, 'message' => implode(' ', $errors)], 422);
}
if (!rate_limit('review', client_ip(), 5, 3600)) {
    json_response(['ok' => false, 'message' => 'Too many reviews submitted. Please try later.'], 429);
}
if (db_val("SELECT COUNT(*) FROM reviews WHERE product_id = ? AND author_email = ? AND status <> 'rejected'", [$pid, $email])) {
    json_response(['ok' => false, 'message' => 'You have already reviewed this product.'], 422);
}
$verified = (int) db_val(
    "SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.email = ? AND o.status = 'delivered'",
    [$pid, $email]
) > 0;
db_insert('reviews', [
    'product_id' => $pid, 'customer_id' => customer_id(), 'author_name' => $name, 'author_email' => $email,
    'rating' => $rating, 'title' => $title ?: null, 'body' => $body, 'status' => 'pending',
    'is_verified_purchase' => $verified ? 1 : 0, 'ip_address' => client_ip(),
]);
json_response(['ok' => true, 'message' => 'Thank you! Your review will appear once it has been approved.']);
