<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$itemId = (int)($_POST['item_id'] ?? 0);
$qty = max(0, (int)($_POST['qty'] ?? 1));

$types = '';
$params = [];
$ownerClause = cart_owner_clause($types, $params);
$sql = "SELECT ci.*, IFNULL(pv.stock_qty, p.stock_qty) AS avail FROM cart_items ci JOIN products p ON p.id = ci.product_id LEFT JOIN product_variations pv ON pv.id = ci.variation_id WHERE ci.id = ? AND ci.$ownerClause";
$stmt = mysqli_prepare($mysqli, $sql);
mysqli_stmt_bind_param($stmt, 'i' . $types, $itemId, ...$params);
mysqli_stmt_execute($stmt);
$item = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$item) { echo json_encode(['success' => false, 'message' => 'Item not found.']); exit; }

if ($qty === 0) {
    mysqli_query($mysqli, "DELETE FROM cart_items WHERE id = " . (int)$itemId);
} else {
    $qty = min($qty, max(1, (int)$item['avail']));
    $stmt = mysqli_prepare($mysqli, "UPDATE cart_items SET qty = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $qty, $itemId);
    mysqli_stmt_execute($stmt);
}

$items = get_cart_items();
echo json_encode(['success' => true, 'cart_count' => cart_count(), 'subtotal' => cart_totals($items), 'subtotal_formatted' => format_price(cart_totals($items))]);
