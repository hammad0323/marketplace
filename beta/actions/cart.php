<?php
require __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

if (!current_customer()) {
    echo json_encode(['success' => false, 'message' => 'Please login to use the cart.', 'login_required' => true]);
    exit;
}
$customer = current_customer();
$do = $_POST['do'] ?? $_GET['do'] ?? '';

if ($do === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $variationId = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    $product = db_fetch_one("SELECT p.*, s.status as shop_status FROM products p JOIN shops s ON s.id=p.shop_id WHERE p.id=?", 'i', [$productId]);
    if (!$product || $product['status'] !== 'active' || $product['shop_status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'This product is not available.']); exit;
    }
    if ($product['stock_status'] !== 'in_stock') {
        echo json_encode(['success' => false, 'message' => 'This product is out of stock.']); exit;
    }
    $cart = get_or_create_cart($customer['id']);
    $existing = db_fetch_one("SELECT * FROM cart_items WHERE cart_id=? AND product_id=? AND " . ($variationId ? "variation_id=?" : "variation_id IS NULL"),
        $variationId ? 'iii' : 'ii', $variationId ? [$cart['id'], $productId, $variationId] : [$cart['id'], $productId]);

    if ($existing) {
        db_exec("UPDATE cart_items SET quantity = quantity + ? WHERE id = ?", 'ii', [$qty, $existing['id']]);
    } else {
        db_insert("INSERT INTO cart_items (cart_id, product_id, variation_id, quantity) VALUES (?,?,?,?)",
            'iiii', [$cart['id'], $productId, $variationId, $qty]);
    }
    $grouped = get_cart_items_grouped($customer['id']);
    $totals = cart_totals($grouped);
    echo json_encode(['success' => true, 'message' => 'Added to cart.', 'cart_count' => $totals['count']]);
    exit;
}

if ($do === 'update') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    db_exec("UPDATE cart_items ci JOIN carts c ON c.id=ci.cart_id SET ci.quantity=? WHERE ci.id=? AND c.customer_id=?",
        'iii', [$qty, $itemId, $customer['id']]);
    $grouped = get_cart_items_grouped($customer['id']);
    $totals = cart_totals($grouped);
    echo json_encode(['success' => true, 'subtotal' => $totals['subtotal'], 'cart_count' => $totals['count']]);
    exit;
}

if ($do === 'remove') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    db_exec("DELETE ci FROM cart_items ci JOIN carts c ON c.id=ci.cart_id WHERE ci.id=? AND c.customer_id=?", 'ii', [$itemId, $customer['id']]);
    $grouped = get_cart_items_grouped($customer['id']);
    $totals = cart_totals($grouped);
    echo json_encode(['success' => true, 'message' => 'Item removed.', 'subtotal' => $totals['subtotal'], 'cart_count' => $totals['count']]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
