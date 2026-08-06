<?php
require __DIR__ . '/../config/config.php';

mp_require_admin();

$banners = mp_all_banners();

$pageTitle = 'Homepage Banners';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Homepage Banners</h1>
<p style="color:var(--ink-500); margin-top:-.5rem;">Controls the hero heading/subtitle/CTA on the homepage and each marketplace landing page. <a href="banner-form.php">Add a banner &rarr;</a></p>

<div class="admin-panel">
    <?php if (!$banners): ?>
        <p>No banners yet — the hardcoded defaults on each page are shown instead.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Shown On</th><th>Title</th><th>CTA</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($banners as $banner): ?>
                <tr>
                    <td><?= mp_e($banner['marketplace_name'] ?? 'Main Homepage') ?></td>
                    <td><?= mp_e($banner['title']) ?></td>
                    <td><?= mp_e($banner['cta_label'] ?? '—') ?></td>
                    <td>
                        <form method="post" action="banner-toggle-active.php" class="inline-form">
                            <?= mp_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $banner['id'] ?>">
                            <button type="submit" class="status-chip status-<?= $banner['is_active'] ? 'completed' : 'cancelled' ?>" style="border:none; cursor:pointer;">
                                <?= $banner['is_active'] ? 'Active' : 'Disabled' ?>
                            </button>
                        </form>
                    </td>
                    <td style="white-space:nowrap;">
                        <a href="banner-form.php?id=<?= (int) $banner['id'] ?>">Edit</a>
                        &nbsp;·&nbsp;
                        <form method="post" action="banner-delete.php" class="inline-form" onsubmit="return confirm('Delete this banner?');">
                            <?= mp_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $banner['id'] ?>">
                            <button type="submit" class="link-button" style="color:var(--danger);">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
