<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$itemId = (int)($_POST['item_id'] ?? 0);
$types = '';
$params = [];
$ownerClause = cart_owner_clause($types, $params);
$stmt = mysqli_prepare($mysqli, "DELETE FROM cart_items WHERE id = ? AND $ownerClause");
mysqli_stmt_bind_param($stmt, 'i' . $types, $itemId, ...$params);
mysqli_stmt_execute($stmt);

$items = get_cart_items();
echo json_encode(['success' => true, 'cart_count' => cart_count(), 'subtotal_formatted' => format_price(cart_totals($items))]);
