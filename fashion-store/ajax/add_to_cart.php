<?php
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$productId = (int)($_POST['product_id'] ?? 0);
$variationId = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
$qty = max(1, (int)($_POST['qty'] ?? 1));

$product = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM products WHERE id = $productId AND status = 'active'"));
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

$stock = $product['stock_qty'];
if ($variationId) {
    $var = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM product_variations WHERE id = $variationId AND product_id = $productId"));
    if (!$var) { echo json_encode(['success' => false, 'message' => 'Invalid variation.']); exit; }
    $stock = $var['stock_qty'];
}

if ($product['stock_status'] !== 'backorder' && $stock < $qty) {
    echo json_encode(['success' => false, 'message' => 'Not enough stock available.']);
    exit;
}

$types = '';
$params = [];
$ownerClause = cart_owner_clause($types, $params);

$sql = "SELECT id, qty FROM cart_items WHERE product_id = ? AND " . $ownerClause . " AND " . ($variationId ? "variation_id = ?" : "variation_id IS NULL");
$stmtTypes = 'i' . $types . ($variationId ? 'i' : '');
$stmtParams = array_merge([$productId], $params, $variationId ? [$variationId] : []);
$stmt = mysqli_prepare($mysqli, $sql);
mysqli_stmt_bind_param($stmt, $stmtTypes, ...$stmtParams);
mysqli_stmt_execute($stmt);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($existing) {
    $newQty = $existing['qty'] + $qty;
    $u = mysqli_prepare($mysqli, "UPDATE cart_items SET qty = ? WHERE id = ?");
    mysqli_stmt_bind_param($u, 'ii', $newQty, $existing['id']);
    mysqli_stmt_execute($u);
} else {
    if (customer_logged_in()) {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO cart_items (customer_id, product_id, variation_id, qty) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'iiii', $_SESSION['customer_id'], $productId, $variationId, $qty);
    } else {
        $sid = cart_session_id();
        $stmt = mysqli_prepare($mysqli, "INSERT INTO cart_items (session_id, product_id, variation_id, qty) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'siii', $sid, $productId, $variationId, $qty);
    }
    mysqli_stmt_execute($stmt);
}

echo json_encode(['success' => true, 'cart_count' => cart_count()]);
