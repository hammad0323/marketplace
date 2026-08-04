<?php
mp_require_admin();

$pending = mp_pending_vendors();
$all = mp_all_vendors();

$pageTitle = 'Vendor Approvals';
require __DIR__ . '/_header.php';
?>

<h1>Vendor Approvals</h1>

<div class="admin-panel">
    <h2>Pending (<?= count($pending) ?>)</h2>
    <?php if (!$pending): ?>
        <p>No pending vendor applications.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Store</th><th>Marketplace</th><th>Email</th><th>Applied</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $vendor): ?>
                <tr>
                    <td><?= mp_e($vendor['store_name']) ?></td>
                    <td><?= mp_e($vendor['marketplace_name']) ?></td>
                    <td><?= mp_e($vendor['email']) ?></td>
                    <td><?= mp_e($vendor['created_at']) ?></td>
                    <td>
                        <form method="post" action="/admin/vendors/<?= (int) $vendor['id'] ?>/approve" class="inline-form">
                            <?= mp_csrf_field() ?>
                            <button type="submit" class="btn">Approve</button>
                        </form>
                        <form method="post" action="/admin/vendors/<?= (int) $vendor['id'] ?>/reject" class="inline-form" onsubmit="return promptReject(this);">
                            <?= mp_csrf_field() ?>
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="admin-panel">
    <h2>All Vendors</h2>
    <table class="admin-table">
        <thead><tr><th>Store</th><th>Email</th><th>Status</th><th>Verified</th><th>Featured</th></tr></thead>
        <tbody>
        <?php foreach ($all as $vendor): ?>
            <tr>
                <td><?= mp_e($vendor['store_name']) ?></td>
                <td><?= mp_e($vendor['email']) ?></td>
                <td><span class="badge badge-<?= mp_e($vendor['status']) ?>"><?= mp_e(ucfirst($vendor['status'])) ?></span></td>
                <td><?= $vendor['is_verified'] ? '✔' : '—' ?></td>
                <td><?= $vendor['is_featured'] ? '✔' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function promptReject(form) {
    var reason = prompt('Reason for rejection (sent to the vendor):');
    if (reason === null || reason.trim() === '') { return false; }
    form.reason.value = reason;
    return true;
}
</script>

<?php require __DIR__ . '/_footer.php'; ?>
