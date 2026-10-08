<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) redirect('');
require_csrf();
$p = row('SELECT id, slug FROM products WHERE id = ? AND status = 1', [(int)post('product_id')]);
if (!$p) redirect('');
$name = mb_substr(post('name'), 0, 120);
$comment = mb_substr(post('comment'), 0, 1500);
$rating = max(1, min(5, (int)post('rating', 5)));
if ($name === '' || mb_strlen($comment) < 3) {
    flash('error', 'Please enter your name and a short review.');
} elseif (($_SESSION['reviewed'][$p['id']] ?? 0) > time() - 3600) {
    flash('info', 'You have already reviewed this product recently.');
} else {
    q('INSERT INTO reviews (product_id, name, rating, comment, status) VALUES (?, ?, ?, ?, 0)', [$p['id'], $name, $rating, $comment]);
    $_SESSION['reviewed'][$p['id']] = time();
    flash('success', 'Thank you! Your review will appear once approved.');
}
redirect('product/' . $p['slug'] . '#reviews');
