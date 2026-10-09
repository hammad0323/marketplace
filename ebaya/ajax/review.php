<?php
/** Customer product review (moderated before publishing). */
if (!defined('EBAYA')) { http_response_code(403); exit; }
if (!is_post()) json_out(['ok' => false], 405);
csrf_check();
$c = current_customer();
if (!$c) json_out(['ok' => false, 'message' => 'Please sign in to write a review.'], 401);
rate_limit_or_fail('review', 5, 3600);
$pid = (int)post('product_id');
$rating = (int)post('rating');
$body = trim(strip_tags((string)post('body')));
if (!db_val("SELECT id FROM products WHERE id = ? AND status = 'published'", [$pid])) json_out(['ok' => false, 'message' => 'Product not found.']);
if ($rating < 1 || $rating > 5) json_out(['ok' => false, 'message' => 'Please choose a star rating.']);
if (!v_len($body, 10, 3000)) json_out(['ok' => false, 'message' => 'Please write at least a few words (10+ characters).']);
if (db_val('SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?', [$pid, (int)$c['id']])) json_out(['ok' => false, 'message' => 'You have already reviewed this piece.']);
$verified = (bool)db_val("SELECT oi.id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = ? AND o.customer_id = ? AND o.status = 'delivered' LIMIT 1", [$pid, (int)$c['id']]);
db_insert('INSERT INTO reviews (product_id, customer_id, name, rating, title, body, verified_purchase) VALUES (?, ?, ?, ?, ?, ?, ?)',
    [$pid, (int)$c['id'], strtok($c['name'], ' ') . ' ' . mb_substr((string)strtok(' '), 0, 1), $rating, mb_substr(trim(strip_tags((string)post('title'))), 0, 190) ?: null, $body, $verified ? 1 : 0]);
json_out(['ok' => true, 'message' => 'Thank you! Your review will appear once approved.']);
