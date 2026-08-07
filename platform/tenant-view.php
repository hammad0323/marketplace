<?php
require __DIR__ . '/../config/config.php';

mp_require_platform_admin();

$tenant = mp_find_tenant((int) ($_GET['id'] ?? 0));
if (!$tenant) {
    mp_flash('error', 'No such tenant.');
    mp_redirect('tenants.php');
}

$counts = mp_platform_tenant_counts($tenant['id']);

$pageTitle = $tenant['business_name'];
require __DIR__ . '/../templates/platform-header.php';
?>

<h1><?= mp_e($tenant['business_name']) ?></h1>
<p style="color:var(--ink-500); margin-top:-.5rem;">
    <?= mp_e($tenant['subdomain']) ?>.<?= mp_e(APP_BASE_DOMAIN) ?> &middot;
    Signed up <?= mp_e(date('M j, Y', strtotime($tenant['created_at']))) ?>
</p>

<div class="admin-stat-grid">
    <div class="admin-stat"><strong><?= $counts['vendors'] ?></strong>Vendors</div>
    <div class="admin-stat"><strong><?= $counts['customers'] ?></strong>Customers</div>
    <div class="admin-stat"><strong><?= $counts['products'] ?></strong>Products</div>
    <div class="admin-stat"><strong><?= $counts['orders'] ?></strong>Orders</div>
</div>

<div class="admin-panel" style="margin-top:1.5rem;">
    <h2 style="margin-top:0;">Account</h2>
    <table class="admin-table">
        <tbody>
            <tr><th>Owner email</th><td><?= mp_e($tenant['owner_email']) ?></td></tr>
            <tr><th>Plan</th><td><span class="badge"><?= mp_e(ucfirst($tenant['plan'])) ?></span></td></tr>
            <tr><th>Status</th><td><span class="status-chip status-<?= $tenant['status'] === 'suspended' ? 'cancelled' : ($tenant['status'] === 'active' ? 'completed' : 'pending') ?>"><?= mp_e(ucfirst($tenant['status'])) ?></span></td></tr>
            <?php if ($tenant['status'] === 'suspended'): ?>
                <tr><th>Suspended</th><td><?= mp_e(date('M j, Y g:i A', strtotime($tenant['suspended_at']))) ?> &mdash; <?= mp_e($tenant['suspended_reason'] ?: 'No reason given') ?></td></tr>
            <?php endif; ?>
            <tr><th>Revenue</th><td><?= mp_currency($counts['revenue']) ?></td></tr>
        </tbody>
    </table>

    <div style="margin-top:1.25rem;">
        <?php if ($tenant['status'] === 'suspended'): ?>
            <form method="post" action="tenant-activate.php" class="inline-form">
                <?= mp_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $tenant['id'] ?>">
                <button type="submit" class="btn">Reactivate Tenant</button>
            </form>
        <?php else: ?>
            <form method="post" action="tenant-suspend.php" class="inline-form" onsubmit="return promptSuspend(this);">
                <?= mp_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $tenant['id'] ?>">
                <input type="hidden" name="reason" value="">
                <button type="submit" class="btn btn-danger">Suspend Tenant</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function promptSuspend(form) {
    var reason = prompt('Reason for suspending this tenant:');
    if (reason === null || reason.trim() === '') { return false; }
    form.reason.value = reason;
    return true;
}
</script>

<?php require __DIR__ . '/../templates/platform-footer.php'; ?>
