<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();

$totalRevenue = mp_total_revenue();
$totalOrders = mp_count_orders();
$totalCustomers = mp_count_customers();
$totalVendors = mp_count_approved_vendors();
$ordersByStatus = mp_orders_count_by_status();
$revenueByDay = mp_revenue_by_day(14);
$topVendors = mp_top_vendors_by_revenue(5);
$topProducts = mp_top_products_by_quantity(5);
$lowStock = mp_low_stock_products(5, 10);

$pageTitle = 'Reports';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Reports</h1>

<div class="admin-stat-grid">
    <div class="admin-stat"><strong><?= mp_currency($totalRevenue) ?></strong>Total Revenue</div>
    <div class="admin-stat"><strong><?= $totalOrders ?></strong>Total Orders</div>
    <div class="admin-stat"><strong><?= $totalCustomers ?></strong>Customers</div>
    <div class="admin-stat"><strong><?= $totalVendors ?></strong>Approved Vendors</div>
</div>

<div class="report-grid">
    <div class="admin-panel">
        <h2 style="margin-top:0;">Revenue — Last 14 Days</h2>
        <canvas id="revenue-chart" height="220"></canvas>
    </div>
    <div class="admin-panel">
        <h2 style="margin-top:0;">Orders by Status</h2>
        <canvas id="status-chart" height="220"></canvas>
    </div>
</div>

<div class="report-grid">
    <div class="admin-panel">
        <h2 style="margin-top:0;">Top Vendors by Revenue</h2>
        <?php if (!$topVendors): ?>
            <p>No sales yet.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>Vendor</th><th>Items Sold</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topVendors as $row): ?>
                    <tr>
                        <td><?= mp_e($row['store_name']) ?></td>
                        <td><?= (int) $row['items_sold'] ?></td>
                        <td><?= mp_currency((float) $row['revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <h2 style="margin-top:0;">Top Products by Units Sold</h2>
        <?php if (!$topProducts): ?>
            <p>No sales yet.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topProducts as $row): ?>
                    <tr>
                        <td><?= mp_e($row['product_title']) ?></td>
                        <td><?= (int) $row['units_sold'] ?></td>
                        <td><?= mp_currency((float) $row['revenue']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="admin-panel">
    <h2 style="margin-top:0;">Low Stock (5 or fewer units)</h2>
    <?php if (!$lowStock): ?>
        <p>Nothing running low right now.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Product</th><th>Vendor</th><th>Stock</th></tr></thead>
            <tbody>
            <?php foreach ($lowStock as $product): ?>
                <tr>
                    <td><?= mp_e($product['title']) ?></td>
                    <td><?= mp_e($product['store_name']) ?></td>
                    <td><span class="status-chip status-<?= $product['stock_quantity'] == 0 ? 'cancelled' : 'pending' ?>"><?= (int) $product['stock_quantity'] ?> left</span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function () {
    var revenueLabels = <?= json_encode(array_map(fn ($r) => date('M j', strtotime($r['day'])), $revenueByDay)) ?>;
    var revenueData = <?= json_encode(array_map(fn ($r) => $r['revenue'], $revenueByDay)) ?>;
    var ordersData = <?= json_encode(array_map(fn ($r) => $r['orders'], $revenueByDay)) ?>;

    new Chart(document.getElementById('revenue-chart'), {
        type: 'line',
        data: {
            labels: revenueLabels,
            datasets: [
                { label: 'Revenue', data: revenueData, borderColor: '#d1502f', backgroundColor: 'rgba(209,80,47,.12)', fill: true, tension: .35, yAxisID: 'y' },
                { label: 'Orders', data: ordersData, borderColor: '#167d68', backgroundColor: 'rgba(22,125,104,.12)', fill: true, tension: .35, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', title: { display: true, text: 'Revenue' } },
                y1: { position: 'right', title: { display: true, text: 'Orders' }, grid: { drawOnChartArea: false } }
            }
        }
    });

    new Chart(document.getElementById('status-chart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Processing', 'Completed', 'Cancelled'],
            datasets: [{
                data: [
                    <?= (int) $ordersByStatus['pending'] ?>,
                    <?= (int) $ordersByStatus['processing'] ?>,
                    <?= (int) $ordersByStatus['completed'] ?>,
                    <?= (int) $ordersByStatus['cancelled'] ?>
                ],
                backgroundColor: ['#c79a4b', '#2563a8', '#167d68', '#c23b2c']
            }]
        },
        options: { responsive: true }
    });
})();
</script>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
