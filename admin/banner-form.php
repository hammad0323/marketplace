<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();

$banner = isset($_GET['id']) ? mp_find_banner((int) $_GET['id']) : null;
if (isset($_GET['id']) && !$banner) {
    require __DIR__ . '/../404.php';
    return;
}

$marketplaceTypes = mp_db_fetch_all('SELECT * FROM marketplace_types ORDER BY id ASC');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        mp_flash('error', 'Title is required.');
        mp_redirect('banner-form.php' . ($banner ? '?id=' . $banner['id'] : ''));
    }

    $data = [
        'marketplace_type_id' => $_POST['marketplace_type_id'] !== '' ? (int) $_POST['marketplace_type_id'] : null,
        'title'               => $title,
        'subtitle'            => trim($_POST['subtitle'] ?? '') ?: null,
        'cta_label'           => trim($_POST['cta_label'] ?? '') ?: null,
        'cta_url'             => trim($_POST['cta_url'] ?? '') ?: null,
        'sort_order'          => (int) ($_POST['sort_order'] ?? 0),
    ];

    if ($banner) {
        mp_update_banner($banner['id'], $data);
        mp_log_activity('admin', $admin['id'], 'banner.updated', 'banner', $banner['id'], $title);
        mp_flash('success', 'Banner updated.');
    } else {
        $data['is_active'] = 1;
        $bannerId = mp_insert_banner($data);
        mp_log_activity('admin', $admin['id'], 'banner.created', 'banner', $bannerId, $title);
        mp_flash('success', 'Banner created.');
    }

    mp_redirect('banners.php');
}

$pageTitle = $banner ? 'Edit Banner' : 'Add Banner';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1><?= $banner ? 'Edit Banner' : 'Add Banner' ?></h1>

<div class="admin-panel">
    <form method="post" action="banner-form.php<?= $banner ? '?id=' . (int) $banner['id'] : '' ?>">
        <?= mp_csrf_field() ?>
        <div class="form-group">
            <label for="marketplace_type_id">Shown On</label>
            <select id="marketplace_type_id" name="marketplace_type_id">
                <option value="">Main Homepage</option>
                <?php foreach ($marketplaceTypes as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" <?= $banner && (int) $banner['marketplace_type_id'] === (int) $type['id'] ? 'selected' : '' ?>><?= mp_e($type['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required value="<?= mp_e($banner['title'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="subtitle">Subtitle</label>
            <textarea id="subtitle" name="subtitle" rows="3"><?= mp_e($banner['subtitle'] ?? '') ?></textarea>
        </div>
        <div class="checkout-address-grid">
            <div class="form-group">
                <label for="cta_label">Button Label</label>
                <input type="text" id="cta_label" name="cta_label" value="<?= mp_e($banner['cta_label'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="cta_url">Button Link</label>
                <input type="text" id="cta_url" name="cta_url" value="<?= mp_e($banner['cta_url'] ?? '') ?>" placeholder="/artisan/index.php">
            </div>
            <div class="form-group">
                <label for="sort_order">Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" min="0" value="<?= (int) ($banner['sort_order'] ?? 0) ?>">
            </div>
        </div>
        <button type="submit" class="btn"><?= $banner ? 'Save Changes' : 'Create Banner' ?></button>
        <a class="btn btn-secondary" href="banners.php">Cancel</a>
    </form>
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
