<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$range = $_GET['range'] ?? '30days';
$rangeMap = ['today' => 'DATE(created_at) = CURDATE()', 'yesterday' => 'DATE(created_at) = CURDATE() - INTERVAL 1 DAY',
    '7days' => 'created_at >= CURDATE() - INTERVAL 7 DAY', '30days' => 'created_at >= CURDATE() - INTERVAL 30 DAY',
    'this_month' => 'MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())',
    'last_month' => 'MONTH(created_at)=MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(created_at)=YEAR(CURDATE() - INTERVAL 1 MONTH)'];
$condition = $rangeMap[$range] ?? $rangeMap['30days'];

$grossSales = db_fetch_one("SELECT COALESCE(SUM(grand_total),0) t FROM orders WHERE $condition")['t'];
$vendorRevenue = db_fetch_one("SELECT COALESCE(SUM(so.vendor_earning),0) t FROM shop_orders so JOIN orders o ON o.id=so.order_id WHERE o.$condition")['t'];
$commission = db_fetch_one("SELECT COALESCE(SUM(so.commission_total),0) t FROM shop_orders so JOIN orders o ON o.id=so.order_id WHERE o.$condition")['t'];
$refunds = db_fetch_one("SELECT COALESCE(SUM(grand_total),0) t FROM orders WHERE order_status='refunded' AND $condition")['t'];

if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="revenue-report-' . $range . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Metric', 'Amount']);
    fputcsv($out, ['Gross Marketplace Sales', $grossSales]);
    fputcsv($out, ['Vendor Revenue', $vendorRevenue]);
    fputcsv($out, ['Platform Commission', $commission]);
    fputcsv($out, ['Refunds', $refunds]);
    fputcsv($out, ['Net Revenue', $commission - $refunds]);
    fclose($out);
    exit;
}

$dashRole = 'admin'; $pageTitle = 'Reports'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Revenue Report</h1>
  <a href="<?= admin_url('reports/index.php?range=' . $range . '&export=1') ?>" class="btn btn-outline"><i class="fa-solid fa-download"></i> Export CSV</a>
</div>
<div class="filter-tabs">
  <?php foreach (['today'=>'Today','yesterday'=>'Yesterday','7days'=>'7 Days','30days'=>'30 Days','this_month'=>'This Month','last_month'=>'Last Month'] as $k=>$v): ?>
    <a class="<?= $range === $k ? 'active' : '' ?>" href="<?= admin_url('reports/index.php?range=' . $k) ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div class="stat-cards">
  <div class="stat-card"><span class="stat-label">Gross Marketplace Sales</span><span class="stat-value"><?= format_price($grossSales) ?></span></div>
  <div class="stat-card"><span class="stat-label">Vendor Revenue</span><span class="stat-value"><?= format_price($vendorRevenue) ?></span></div>
  <div class="stat-card"><span class="stat-label">Platform Commission</span><span class="stat-value"><?= format_price($commission) ?></span></div>
  <div class="stat-card danger"><span class="stat-label">Refunds</span><span class="stat-value"><?= format_price($refunds) ?></span></div>
  <div class="stat-card success"><span class="stat-label">Net Revenue</span><span class="stat-value"><?= format_price($commission - $refunds) ?></span></div>
</div>
<div class="dash-table-card">
  <h3>Export Other Data</h3>
  <div class="quick-actions">
    <a href="<?= admin_url('reports/export.php?type=orders') ?>" class="qa-btn"><i class="fa-solid fa-receipt"></i> Export Orders CSV</a>
    <a href="<?= admin_url('reports/export.php?type=customers') ?>" class="qa-btn"><i class="fa-solid fa-users"></i> Export Customers CSV</a>
    <a href="<?= admin_url('reports/export.php?type=shops') ?>" class="qa-btn"><i class="fa-solid fa-store"></i> Export Shops CSV</a>
    <a href="<?= admin_url('reports/export.php?type=products') ?>" class="qa-btn"><i class="fa-solid fa-box"></i> Export Products CSV</a>
    <a href="<?= admin_url('reports/export.php?type=commissions') ?>" class="qa-btn"><i class="fa-solid fa-percent"></i> Export Commissions CSV</a>
  </div>
</div>
<div class="dash-table-card">
  <h3>Audit Log (Recent Activity)</h3>
  <table class="dash-table">
    <thead><tr><th>User</th><th>Action</th><th>Module</th><th>Description</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach (db_fetch_all("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 30") as $log): ?>
        <tr><td><?= clean($log['user_name']) ?> (<?= clean($log['user_type']) ?>)</td><td><?= clean($log['action']) ?></td><td><?= clean($log['module']) ?></td>
            <td><?= clean($log['description']) ?></td><td><?= date('M d, Y g:ia', strtotime($log['created_at'])) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
