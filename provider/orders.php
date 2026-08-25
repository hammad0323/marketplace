<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);
if (!$provider) {
    redirect('/provider/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $itemId = (int) ($_POST['order_item_id'] ?? 0);
    $newStatus = clean_input($_POST['status'] ?? '');
    $item = $itemId ? db_select_one($conn, 'SELECT * FROM order_items WHERE id = ? AND provider_id = ?', [$itemId, (int) $provider['id']]) : null;

    if (!$item || !in_array($newStatus, order_item_allowed_transitions($item['status']), true)) {
        flash_set('danger', 'That action is not available for this order item.');
        redirect('/provider/orders.php');
    }

    db_execute($conn, 'UPDATE order_items SET status = ? WHERE id = ?', [$newStatus, $itemId]);
    recalculate_order_status($conn, (int) $item['order_id']);

    $order = db_select_one($conn, 'SELECT customer_id, order_ref FROM orders WHERE id = ?', [(int) $item['order_id']]);
    $notifTitles = [
        'processing' => ['Order is being prepared', 'is being prepared'],
        'shipped' => ['Order shipped', 'has shipped'],
        'delivered' => ['Order delivered', 'was marked delivered'],
        'cancelled' => ['Order item cancelled', 'was cancelled by the seller'],
    ];
    if ($order && isset($notifTitles[$newStatus])) {
        db_execute(
            $conn,
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "order_status", ?, ?, "/customer/orders.php")',
            [(int) $order['customer_id'], $notifTitles[$newStatus][0], $item['title'] . ' (' . $order['order_ref'] . ') ' . $notifTitles[$newStatus][1] . '.']
        );
    }

    log_activity($conn, (int) $user['id'], 'order_item_' . $newStatus, $order['order_ref'] ?? '');
    flash_set('success', 'Order item updated.');
    redirect('/provider/orders.php');
}

$statusFilter = clean_input($_GET['status'] ?? '');
$where = ['oi.provider_id = ?'];
$params = [(int) $provider['id']];
if ($statusFilter !== '') {
    $where[] = 'oi.status = ?';
    $params[] = $statusFilter;
}

$items = db_select(
    $conn,
    'SELECT oi.*, o.order_ref, o.shipping_name, o.shipping_phone, o.shipping_address, o.created_at AS order_created_at, u.name AS customer_name, u.email AS customer_email
     FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN users u ON u.id = o.customer_id
     WHERE ' . implode(' AND ', $where) . ' ORDER BY oi.created_at DESC',
    $params
);

$pageTitle = 'Orders';
$providerActiveTab = 'orders';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px;">
      <div>
        <span class="eyebrow"><i class="bi bi-bag-check"></i> Provider</span>
        <h1 class="section-heading">Orders</h1>
      </div>
      <select onchange="window.location='?status='+this.value" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
          <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <?php require ROOT_PATH . '/includes/provider-tabs.php'; ?>

    <?php if ($items): ?>
      <div class="panel">
        <div class="table-responsive">
        <table class="table-w">
          <thead><tr><th>Order</th><th>Product</th><th>Customer</th><th>Ship to</th><th>Total</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><code><?php echo e($it['order_ref']); ?></code><br><span style="color:var(--ink-mute);font-size:12px;"><?php echo e(format_date($it['order_created_at'])); ?></span></td>
                <td><?php echo e($it['title']); ?><br><span style="color:var(--ink-mute);font-size:12px;">Qty <?php echo (int) $it['quantity']; ?></span></td>
                <td><?php echo e($it['customer_name']); ?><br><span style="color:var(--ink-mute);font-size:12px;"><?php echo e($it['customer_email']); ?></span></td>
                <td style="max-width:200px;font-size:12.5px;color:var(--ink-mute);"><?php echo e($it['shipping_name']); ?><br><?php echo e($it['shipping_address']); ?><br><?php echo e($it['shipping_phone']); ?></td>
                <td><?php echo format_price($it['total_price']); ?></td>
                <td><?php echo status_badge($it['status']); ?></td>
                <td style="text-align:right;white-space:nowrap;">
                  <?php foreach (order_item_allowed_transitions($it['status']) as $next): ?>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Mark this item <?php echo $next; ?>?');">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="order_item_id" value="<?php echo (int) $it['id']; ?>">
                      <input type="hidden" name="status" value="<?php echo $next; ?>">
                      <button type="submit" class="btn-w btn-sm <?php echo $next === 'cancelled' ? 'btn-outline' : 'btn-primary'; ?>"><?php echo ucfirst($next); ?></button>
                    </form>
                  <?php endforeach; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-bag-check"></i></div><h4>No orders yet</h4><p>Orders from customers buying your products will show up here.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
