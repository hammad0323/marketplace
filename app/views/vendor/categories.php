<h1>Selling Categories</h1>

<div class="content-panel">
    <h2>Your Requests</h2>
    <?php if (!$requests): ?>
        <p>You haven't requested any categories yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Category</th><th>Status</th><th>Enabled</th><th>Limit</th><th>Admin Notes</th></tr></thead>
            <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e($request['category_name']) ?></td>
                    <td><span class="badge badge-<?= e($request['status']) ?>"><?= e(ucfirst($request['status'])) ?></span></td>
                    <td><?= $request['is_enabled'] ? 'Yes' : 'No' ?></td>
                    <td><?= $request['usage_limit'] !== null ? (int) $request['usage_limit'] : 'Unlimited' ?></td>
                    <td><?= e($request['admin_notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if ($availableCategories): ?>
<div class="content-panel">
    <h2>Request More Categories</h2>
    <form method="post" action="/vendor/dashboard/categories">
        <?= csrf_field() ?>
        <div class="checkbox-grid">
            <?php foreach ($availableCategories as $category): ?>
                <label><input type="checkbox" name="category_ids[]" value="<?= (int) $category['id'] ?>"> <?= e($category['name']) ?></label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn" style="margin-top:1rem;">Submit Request</button>
    </form>
</div>
<?php endif; ?>
