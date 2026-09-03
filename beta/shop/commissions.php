<?php
require __DIR__ . '/../config/config.php';
require_permission('view_commission');
$shopId = active_shop_id();

$totals = [
    'revenue' => db_fetch_one("SELECT COALESCE(SUM(revenue),0) t FROM commissions WHERE shop_id=?", 'i', [$shopId])['t'],
    'total' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=?", 'i', [$shopId])['t'],
    'paid' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=? AND payment_status='paid'", 'i', [$shopId])['t'],
    'pending' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=? AND payment_status='pending'", 'i', [$shopId])['t'],
    'overdue' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE shop_id=? AND payment_status='overdue'", 'i', [$shopId])['t'],
];
$commissions = db_fetch_all("SELECT * FROM commissions WHERE shop_id=? ORDER BY created_at DESC", 'i', [$shopId]);

$dashRole = 'shop'; $pageTitle = 'Commissions';
$dashUserName = current_shop_owner()['name'] ?? current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Commissions</h1></div>
<div class="stat-cards">
  <div class="stat-card"><span class="stat-label">Total Revenue</span><span class="stat-value"><?= format_price($totals['revenue']) ?></span></div>
  <div class="stat-card"><span class="stat-label">Total Commission</span><span class="stat-value"><?= format_price($totals['total']) ?></span></div>
  <div class="stat-card success"><span class="stat-label">Paid</span><span class="stat-value"><?= format_price($totals['paid']) ?></span></div>
  <div class="stat-card warn"><span class="stat-label">Pending</span><span class="stat-value"><?= format_price($totals['pending']) ?></span></div>
  <div class="stat-card danger"><span class="stat-label">Overdue</span><span class="stat-value"><?= format_price($totals['overdue']) ?></span></div>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Invoice</th><th>Revenue</th><th>Commission</th><th>Due Date</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if ($commissions): foreach ($commissions as $c): ?>
      <tr>
        <td><?= clean($c['invoice_number']) ?></td>
        <td><?= format_price($c['revenue']) ?></td>
        <td><?= format_price($c['commission_amount']) ?></td>
        <td><?= date('M d, Y', strtotime($c['due_date'])) ?></td>
        <td><span class="badge badge-<?= $c['payment_status']==='paid'?'success':($c['payment_status']==='overdue'?'danger':'warn') ?>"><?= clean($c['payment_status']) ?></span></td>
        <td><a href="<?= shop_url('commissions.php?invoice=' . $c['id']) ?>" class="btn btn-sm btn-outline" onclick="window.print();return false;">Print Invoice</a></td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><h3>No commission records</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (!empty($_GET['invoice'])):
    $inv = db_fetch_one("SELECT cm.*, s.shop_name FROM commissions cm JOIN shops s ON s.id=cm.shop_id WHERE cm.id=? AND cm.shop_id=?", 'ii', [(int)$_GET['invoice'], $shopId]);
    if ($inv): ?>
<div class="dash-form-card invoice-print">
  <h2><?= clean(site_name()) ?></h2>
  <p><strong>Invoice:</strong> <?= clean($inv['invoice_number']) ?> &nbsp; <strong>Shop:</strong> <?= clean($inv['shop_name']) ?></p>
  <p><strong>Date:</strong> <?= date('M d, Y', strtotime($inv['created_at'])) ?> &nbsp; <strong>Due:</strong> <?= date('M d, Y', strtotime($inv['due_date'])) ?></p>
  <p><strong>Revenue:</strong> <?= format_price($inv['revenue']) ?></p>
  <p><strong>Amount Due:</strong> <?= format_price($inv['commission_amount']) ?></p>
  <p><strong>Status:</strong> <?= clean($inv['payment_status']) ?></p>
</div>
<?php endif; endif; ?>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
