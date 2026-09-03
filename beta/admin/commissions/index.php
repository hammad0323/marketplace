<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$totals = [
    'total' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions")['t'],
    'paid' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE payment_status='paid'")['t'],
    'pending' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE payment_status='pending'")['t'],
    'overdue' => db_fetch_one("SELECT COALESCE(SUM(commission_amount),0) t FROM commissions WHERE payment_status='overdue'")['t'],
];

$statusFilter = $_GET['status'] ?? '';
$where = $statusFilter ? "WHERE cm.payment_status = ?" : "";
$params = $statusFilter ? [$statusFilter] : []; $types = $statusFilter ? 's' : '';

$count = db_fetch_one("SELECT COUNT(*) c FROM commissions cm $where", $types, $params);
$pagination = paginate((int)$count['c'], 20);
$commissions = db_fetch_all("SELECT cm.*, s.shop_name FROM commissions cm JOIN shops s ON s.id=cm.shop_id
    $where ORDER BY cm.created_at DESC LIMIT ? OFFSET ?", $types . 'ii', [...$params, $pagination['perPage'], $pagination['offset']]);

$dashRole = 'admin'; $pageTitle = 'Commissions'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Commissions</h1></div>
<div class="stat-cards">
  <div class="stat-card"><span class="stat-label">Total Commission</span><span class="stat-value"><?= format_price($totals['total']) ?></span></div>
  <div class="stat-card success"><span class="stat-label">Paid</span><span class="stat-value"><?= format_price($totals['paid']) ?></span></div>
  <div class="stat-card warn"><span class="stat-label">Pending</span><span class="stat-value"><?= format_price($totals['pending']) ?></span></div>
  <div class="stat-card danger"><span class="stat-label">Overdue</span><span class="stat-value"><?= format_price($totals['overdue']) ?></span></div>
</div>
<div class="filter-tabs">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $k => $v): ?>
    <a class="<?= $statusFilter === $k ? 'active' : '' ?>" href="<?= admin_url('commissions/index.php?status=' . $k) ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Invoice</th><th>Shop</th><th>Revenue</th><th>Commission</th><th>Due Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php if ($commissions): foreach ($commissions as $c): ?>
      <tr>
        <td><?= clean($c['invoice_number']) ?></td>
        <td><?= clean($c['shop_name']) ?></td>
        <td><?= format_price($c['revenue']) ?></td>
        <td><?= format_price($c['commission_amount']) ?></td>
        <td><?= date('M d, Y', strtotime($c['due_date'])) ?></td>
        <td><span class="badge badge-<?= $c['payment_status'] === 'paid' ? 'success' : ($c['payment_status'] === 'overdue' ? 'danger' : 'warn') ?>"><?= clean($c['payment_status']) ?></span></td>
      </tr>
    <?php endforeach; else: ?><tr><td colspan="6"><div class="empty-state"><h3>No commission records</h3></div></td></tr><?php endif; ?>
    </tbody>
  </table>
  <?= pagination_links($pagination, admin_url('commissions/index.php?status=' . $statusFilter)) ?>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
