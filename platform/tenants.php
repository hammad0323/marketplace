<?php
require __DIR__ . '/../config/config.php';

mp_require_platform_admin();

$tenants = mp_platform_all_tenants();

$pageTitle = 'Tenants';
require __DIR__ . '/../templates/platform-header.php';
?>

<h1>Tenants</h1>

<div class="admin-panel">
    <?php if (!$tenants): ?>
        <p>No tenants yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Business</th><th>Subdomain</th><th>Plan</th><th>Status</th><th>Owner Email</th><th>Signed Up</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tenants as $tenant): ?>
                <tr>
                    <td><?= mp_e($tenant['business_name']) ?></td>
                    <td><?= mp_e($tenant['subdomain']) ?>.<?= mp_e(APP_BASE_DOMAIN) ?></td>
                    <td><span class="badge"><?= mp_e(ucfirst($tenant['plan'])) ?></span></td>
                    <td><span class="status-chip status-<?= $tenant['status'] === 'suspended' ? 'cancelled' : ($tenant['status'] === 'active' ? 'completed' : 'pending') ?>"><?= mp_e(ucfirst($tenant['status'])) ?></span></td>
                    <td><?= mp_e($tenant['owner_email']) ?></td>
                    <td style="white-space:nowrap;"><?= mp_e(date('M j, Y', strtotime($tenant['created_at']))) ?></td>
                    <td><a href="tenant-view.php?id=<?= (int) $tenant['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/platform-footer.php'; ?>
