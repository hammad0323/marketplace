<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

$page = max(1, (int) ($_GET['page'] ?? 1));
$pg = paginate($conn, 'SELECT COUNT(*) FROM orders WHERE customer_id = ?', [(int) $user['id']], $page, 10);

$orders = db_select(
    $conn,
    "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    [(int) $user['id']]
);

$itemsByOrder = [];
if ($orders) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    foreach (db_select($conn, "SELECT oi.*, p.business_name FROM order_items oi JOIN providers p ON p.id = oi.provider_id WHERE oi.order_id IN ($placeholders) ORDER BY oi.id", $orderIds) as $oi) {
        $itemsByOrder[$oi['order_id']][] = $oi;
    }
}

$pageTitle = 'My Orders';
$customerActiveTab = 'orders';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-bag-check"></i> Customer</span>
      <h1 class="section-heading">My orders</h1>
      <p class="section-sub">Everything you've bought from stores on <?php echo e($siteName); ?>.</p>
    </div>

    <?php require ROOT_PATH . '/includes/customer-tabs.php'; ?>

    <?php if ($orders): ?>
      <?php foreach ($orders as $order): ?>
        <div class="panel">
          <div class="panel-head">
            <div>
              <h3><code><?php echo e($order['order_ref']); ?></code></h3>
              <div style="font-size:12.5px;color:var(--ink-mute);margin-top:2px;"><?php echo e(format_date($order['created_at'], 'M j, Y g:i A')); ?></div>
            </div>
            <div style="text-align:right;">
              <?php echo status_badge($order['status']); ?>
              <div style="font-weight:800;margin-top:6px;"><?php echo format_price($order['total_amount']); ?></div>
            </div>
          </div>
          <?php foreach ($itemsByOrder[$order['id']] ?? [] as $oi): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13.5px;">
              <div>
                <strong><?php echo e($oi['title']); ?></strong>
                <span style="color:var(--ink-mute);"> × <?php echo (int) $oi['quantity']; ?> · from <?php echo e($oi['business_name']); ?></span>
              </div>
              <div style="display:flex;align-items:center;gap:10px;">
                <?php echo status_badge($oi['status']); ?>
                <strong><?php echo format_price($oi['total_price']); ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if ($order['shipping_address']): ?>
            <div style="font-size:12.5px;color:var(--ink-mute);margin-top:10px;"><i class="bi bi-geo-alt"></i> Shipping to <?php echo e($order['shipping_address']); ?></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($pg['total_pages'] > 1): ?>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:18px;">
          <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
            <a href="?page=<?php echo $i; ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-bag-check"></i></div>
        <h4>No orders yet</h4>
        <p>Browse stores and buy something — your orders will show up here.</p>
        <a href="<?php echo url('/pages/search.php'); ?>?category=store" class="btn-w btn-primary">Browse stores</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
