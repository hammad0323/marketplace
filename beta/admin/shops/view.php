<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$shop = db_fetch_one("SELECT s.*, o.name as owner_name, o.email as owner_email, o.phone as owner_phone FROM shops s
    JOIN shop_owners o ON o.id = s.owner_id WHERE s.id=?", 'i', [$id]);
if (!$shop) { flash('error', 'Shop not found.'); redirect(admin_url('shops/index.php')); }

$productCount = db_fetch_one("SELECT COUNT(*) c FROM products WHERE shop_id=?", 'i', [$id])['c'];
$orderCount = db_fetch_one("SELECT COUNT(*) c FROM shop_orders WHERE shop_id=?", 'i', [$id])['c'];
$revenue = db_fetch_one("SELECT COALESCE(SUM(subtotal),0) t FROM shop_orders WHERE shop_id=? AND status='delivered'", 'i', [$id])['t'];
$commission = db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=?", 'i', [$id])['t'];
$pendingCommission = db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=? AND payment_status IN ('pending','overdue')", 'i', [$id])['t'];
$staff = db_fetch_all("SELECT * FROM shop_staff WHERE shop_id=?", 'i', [$id]);
$recentOrders = db_fetch_all("SELECT * FROM shop_orders WHERE shop_id=? ORDER BY created_at DESC LIMIT 10", 'i', [$id]);

$dashRole = 'admin'; $pageTitle = $shop['shop_name']; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1><?= clean($shop['shop_name']) ?> <span class="badge badge-<?= $shop['status'] === 'active' ? 'success' : 'warn' ?>"><?= clean($shop['status']) ?></span></h1>
  <a href="<?= base_url('shop.php?slug=' . $shop['slug']) ?>" target="_blank" class="btn btn-outline">View Public Page</a>
</div>
<div class="stat-cards">
  <div class="stat-card"><span class="stat-label">Products</span><span class="stat-value"><?= $productCount ?></span></div>
  <div class="stat-card"><span class="stat-label">Orders</span><span class="stat-value"><?= $orderCount ?></span></div>
  <div class="stat-card"><span class="stat-label">Revenue</span><span class="stat-value"><?= format_price($revenue) ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Commission</span><span class="stat-value"><?= format_price($commission) ?></span></div>
  <div class="stat-card danger"><span class="stat-label">Outstanding Commission</span><span class="stat-value"><?= format_price($pendingCommission) ?></span></div>
</div>
<div class="dash-two-col">
  <div class="dash-table-card">
    <h3>Owner Info</h3>
    <p><strong>Name:</strong> <?= clean($shop['owner_name']) ?></p>
    <p><strong>Email:</strong> <?= clean($shop['owner_email']) ?></p>
    <p><strong>Phone:</strong> <?= clean($shop['owner_phone']) ?></p>
    <p><strong>Address:</strong> <?= clean($shop['business_address']) ?>, <?= clean($shop['city']) ?>, <?= clean($shop['country']) ?></p>
    <?php if ($shop['status_reason']): ?><p><strong>Status Reason:</strong> <?= clean($shop['status_reason']) ?></p><?php endif; ?>
  </div>
  <div class="dash-table-card">
    <h3>Staff (<?= count($staff) ?>)</h3>
    <table class="dash-table">
      <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($staff as $st): ?><tr><td><?= clean($st['name']) ?></td><td><?= clean($st['email']) ?></td><td><?= clean($st['status']) ?></td></tr><?php endforeach; ?>
        <?php if (!$staff): ?><tr><td colspan="3" class="text-muted">No staff yet</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="dash-table-card">
  <h3>Recent Orders</h3>
  <table class="dash-table">
    <thead><tr><th>Order #</th><th>Subtotal</th><th>Commission</th><th>Vendor Earning</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($recentOrders as $o): ?>
        <tr><td><?= clean($o['shop_order_number']) ?></td><td><?= format_price($o['subtotal']) ?></td><td><?= format_price($o['commission_total']) ?></td>
            <td><?= format_price($o['vendor_earning']) ?></td><td><?= clean($o['status']) ?></td><td><?= date('M d, Y', strtotime($o['created_at'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
