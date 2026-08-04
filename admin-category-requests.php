<?php
require __DIR__ . '/config.php';

mp_require_admin();

$pending = mp_pending_category_requests();
$all = mp_all_category_requests();

$pageTitle = 'Category Approvals';
require __DIR__ . '/admin-header.php';
?>

<h1>Category Approvals</h1>

<div class="admin-panel">
    <h2>Pending (<?= count($pending) ?>)</h2>
    <?php if (!$pending): ?>
        <p>No pending category requests.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Store</th><th>Category</th><th>Requested</th><th>Decision</th></tr></thead>
            <tbody>
            <?php foreach ($pending as $request): ?>
                <tr>
                    <td><?= mp_e($request['store_name']) ?></td>
                    <td><?= mp_e($request['category_name']) ?></td>
                    <td><?= mp_e($request['created_at']) ?></td>
                    <td>
                        <form method="post" action="/admin-category-decide.php?id=<?= (int) $request['id'] ?>">
                            <?= mp_csrf_field() ?>
                            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                <input type="text" name="notes" placeholder="Admin notes (optional)" style="width:auto;">
                                <input type="number" name="usage_limit" placeholder="Product limit (optional)" style="width:auto;" min="1">
                                <button type="submit" name="decision" value="approved" class="btn">Approve</button>
                                <button type="submit" name="decision" value="rejected" class="btn btn-danger">Reject</button>
                            </div>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="admin-panel">
    <h2>All Requests</h2>
    <table class="admin-table">
        <thead><tr><th>Store</th><th>Category</th><th>Status</th><th>Limit</th><th>Enabled</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($all as $request): ?>
            <tr>
                <td><?= mp_e($request['store_name']) ?></td>
                <td><?= mp_e($request['category_name']) ?></td>
                <td><span class="badge badge-<?= mp_e($request['status']) ?>"><?= mp_e(ucfirst($request['status'])) ?></span></td>
                <td><?= $request['usage_limit'] !== null ? (int) $request['usage_limit'] : 'Unlimited' ?></td>
                <td><?= $request['is_enabled'] ? 'Yes' : 'No' ?></td>
                <td>
                    <?php if ($request['status'] === 'approved'): ?>
                    <form method="post" action="/admin-category-toggle.php?id=<?= (int) $request['id'] ?>" class="inline-form">
                        <?= mp_csrf_field() ?>
                        <button type="submit" class="link-button"><?= $request['is_enabled'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/admin-footer.php'; ?>
