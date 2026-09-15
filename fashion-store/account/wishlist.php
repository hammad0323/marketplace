<?php
require_once __DIR__ . '/../includes/functions.php';
require_customer_login();
$customer = current_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'move_to_cart') {
    csrf_verify();
    $productId = (int)$_POST['product_id'];
    $body = ['product_id' => $productId, 'qty' => 1];
    // simple direct insert to cart
    $types = ''; $params = [];
    cart_owner_clause($types, $params);
    if (customer_logged_in()) {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO cart_items (customer_id, product_id, qty) VALUES (?,?,1)");
        mysqli_stmt_bind_param($stmt, 'ii', $customer['id'], $productId);
        mysqli_stmt_execute($stmt);
    }
    flash_set('success', 'Moved to cart.');
    redirect(BASE_URL . '/account/wishlist.php');
}

$wishlist = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM wishlists WHERE customer_id = {$customer['id']}"));
$items = [];
if ($wishlist) {
    $res = mysqli_query($mysqli, "SELECT p.* FROM wishlist_items wi JOIN products p ON p.id = wi.product_id WHERE wi.wishlist_id = {$wishlist['id']} ORDER BY wi.created_at DESC");
    while ($p = mysqli_fetch_assoc($res)) $items[] = $p;
}

$pageTitle = 'My Wishlist | ' . get_setting('store_name');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container section-tight">
  <div class="row g-4">
    <div class="col-lg-3"><?php include __DIR__ . '/../includes/account_sidebar.php'; ?></div>
    <div class="col-lg-9">
      <h1 class="h4 font-serif mb-4">My Wishlist</h1>
      <?php if (!$items): ?>
        <p class="text-muted">Your wishlist is empty. <a href="<?= BASE_URL ?>/shop.php">Browse products</a>.</p>
      <?php else: ?>
      <div class="row row-cols-2 row-cols-md-3 g-4">
        <?php foreach ($items as $p): ?>
          <div class="col"><?php render_product_card($mysqli, $p); ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
