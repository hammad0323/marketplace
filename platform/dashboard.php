<?php
require __DIR__ . '/../config/config.php';

mp_require_platform_admin();

$stats = mp_platform_stats();

$pageTitle = 'Platform Dashboard';
require __DIR__ . '/../templates/platform-header.php';
?>

<h1>Platform Dashboard</h1>

<div class="admin-stat-grid">
    <a class="admin-stat" href="tenants.php">
        <strong><?= $stats['total_tenants'] ?></strong>
        Total Tenants
    </a>
    <a class="admin-stat" href="tenants.php">
        <strong><?= $stats['trial_tenants'] ?></strong>
        On Trial
    </a>
    <a class="admin-stat" href="tenants.php">
        <strong><?= $stats['active_tenants'] ?></strong>
        Active
    </a>
    <a class="admin-stat" href="tenants.php">
        <strong><?= $stats['suspended_tenants'] ?></strong>
        Suspended
    </a>
</div>

<div class="admin-stat-grid" style="margin-top:1.5rem;">
    <div class="admin-stat">
        <strong><?= $stats['total_vendors'] ?></strong>
        Vendors (all tenants)
    </div>
    <div class="admin-stat">
        <strong><?= $stats['total_customers'] ?></strong>
        Customers (all tenants)
    </div>
    <div class="admin-stat">
        <strong><?= $stats['total_orders'] ?></strong>
        Orders (all tenants)
    </div>
    <div class="admin-stat">
        <strong><?= mp_currency($stats['total_revenue']) ?></strong>
        Revenue (all tenants)
    </div>
</div>

<?php require __DIR__ . '/../templates/platform-footer.php'; ?>
