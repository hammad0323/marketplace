<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $cartItemId = (int) ($_POST['cart_item_id'] ?? 0);
    $row = $cartItemId ? db_select_one($conn, 'SELECT * FROM cart_items WHERE id = ? AND user_id = ?', [$cartItemId, (int) $user['id']]) : null;

    if (!$row) {
        flash_set('danger', 'Cart item not found.');
        redirect('/customer/cart.php');
    }

    if ($action === 'update') {
        $qty = max(1, (int) ($_POST['quantity'] ?? 1));
        $service = db_select_one($conn, 'SELECT stock_quantity FROM services WHERE id = ?', [(int) $row['service_id']]);
        if ($service && $service['stock_quantity'] !== null) {
            $qty = min($qty, max(1, (int) $service['stock_quantity']));
        }
        db_execute($conn, 'UPDATE cart_items SET quantity = ? WHERE id = ?', [$qty, $cartItemId]);
    } elseif ($action === 'remove') {
        db_execute($conn, 'DELETE FROM cart_items WHERE id = ?', [$cartItemId]);
        flash_set('success', 'Removed from cart.');
    }
    redirect('/customer/cart.php');
}

$items = get_cart_items($conn, (int) $user['id']);

$subtotal = 0;
$tax = 0;
$fee = 0;
$total = 0;
$hasIssue = false;
foreach ($items as &$item) {
    $item['line_base'] = round((float) $item['price'] * (int) $item['quantity'], 2);
    $subtotal += $item['line_base'];
    $item['out_of_stock'] = $item['status'] !== 'approved' || ($item['stock_quantity'] !== null && (int) $item['stock_quantity'] < 1);
    $item['exceeds_stock'] = $item['stock_quantity'] !== null && (int) $item['quantity'] > (int) $item['stock_quantity'];
    if ($item['out_of_stock'] || $item['exceeds_stock']) {
        $hasIssue = true;
    }
}
unset($item);

$taxPercent = (float) (db_select_one($conn, 'SELECT percent FROM taxes WHERE applies_to IN ("all","booking") AND is_active = 1 ORDER BY id LIMIT 1')['percent'] ?? 0);
$feePercent = (float) get_setting($conn, 'service_fee_percent', 0);
$tax = round($subtotal * $taxPercent / 100, 2);
$fee = round($subtotal * $feePercent / 100, 2);
$total = round($subtotal + $tax + $fee, 2);

$itemsByProvider = [];
foreach ($items as $item) {
    $itemsByProvider[$item['business_name']][] = $item;
}

$pageTitle = 'My Cart';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:960px;">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-cart3"></i> Cart</span>
      <h1 class="section-heading">Your cart</h1>
      <p class="section-sub"><?php echo count($items); ?> item<?php echo count($items) === 1 ? '' : 's'; ?> from <?php echo count($itemsByProvider); ?> store<?php echo count($itemsByProvider) === 1 ? '' : 's'; ?>.</p>
    </div>

    <?php if (!$items): ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-cart3"></i></div>
        <h4>Your cart is empty</h4>
        <p>Browse stores and add products to see them here.</p>
        <a href="<?php echo url('/pages/search.php'); ?>?category=store" class="btn-w btn-primary">Browse stores</a>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start;">
        <div>
          <?php foreach ($itemsByProvider as $businessName => $providerItems): ?>
            <div class="panel">
              <div class="panel-head"><h3><i class="bi bi-shop"></i> <?php echo e($businessName); ?></h3></div>
              <?php foreach ($providerItems as $item): ?>
                <div style="display:flex;gap:14px;align-items:center;padding:12px 0;border-bottom:1px solid var(--border);">
                  <div style="width:64px;height:64px;border-radius:10px;background:var(--purple-soft);overflow:hidden;flex-shrink:0;">
                    <?php if ($item['image']): ?><img src="<?php echo e($item['image']); ?>" style="width:100%;height:100%;object-fit:cover;"><?php endif; ?>
                  </div>
                  <div style="flex:1;min-width:0;">
                    <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($item['slug']); ?>" style="font-weight:700;color:var(--ink);"><?php echo e($item['title']); ?></a>
                    <div style="font-size:13.5px;color:var(--ink-mute);margin-top:2px;"><?php echo format_price($item['price']); ?> each</div>
                    <?php if ($item['out_of_stock']): ?>
                      <div style="font-size:12.5px;color:var(--danger);font-weight:600;margin-top:4px;"><i class="bi bi-exclamation-triangle-fill"></i> No longer available</div>
                    <?php elseif ($item['exceeds_stock']): ?>
                      <div style="font-size:12.5px;color:var(--danger);font-weight:600;margin-top:4px;"><i class="bi bi-exclamation-triangle-fill"></i> Only <?php echo (int) $item['stock_quantity']; ?> left — reduce quantity</div>
                    <?php endif; ?>
                  </div>
                  <form method="post" style="display:flex;align-items:center;gap:6px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="cart_item_id" value="<?php echo (int) $item['cart_item_id']; ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="number" name="quantity" value="<?php echo (int) $item['quantity']; ?>" min="1" <?php echo $item['stock_quantity'] !== null ? 'max="' . (int) $item['stock_quantity'] . '"' : ''; ?> style="width:64px;padding:8px;border-radius:8px;border:1.5px solid var(--border);text-align:center;" onchange="this.form.submit()">
                  </form>
                  <strong style="width:90px;text-align:right;"><?php echo format_price($item['line_base']); ?></strong>
                  <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="cart_item_id" value="<?php echo (int) $item['cart_item_id']; ?>">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="panel" style="position:sticky;top:96px;">
          <h3 style="font-size:16px;margin-bottom:14px;">Order summary</h3>
          <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:14px;"><span>Subtotal</span><strong><?php echo format_price($subtotal); ?></strong></div>
          <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:14px;"><span>Service fee</span><strong><?php echo format_price($fee); ?></strong></div>
          <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:14px;"><span>Tax</span><strong><?php echo format_price($tax); ?></strong></div>
          <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid var(--border);margin-top:6px;font-size:16px;"><span style="font-weight:700;">Total</span><strong style="color:var(--purple-600);"><?php echo format_price($total); ?></strong></div>

          <?php if ($hasIssue): ?>
            <div class="alert-w alert-danger" style="margin-top:14px;"><i class="bi bi-exclamation-triangle-fill"></i> Fix the highlighted items before checking out.</div>
            <button type="button" class="btn-w btn-primary btn-block" style="margin-top:8px;" disabled>Proceed to checkout</button>
          <?php else: ?>
            <a href="<?php echo url('/customer/checkout.php'); ?>" class="btn-w btn-primary btn-block" style="margin-top:16px;">Proceed to checkout <i class="bi bi-arrow-right"></i></a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
