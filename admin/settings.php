<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$settingsRes = mysqli_query(db(), 'SELECT setting_key, setting_value FROM site_settings');
$settings = [];
while ($row = mysqli_fetch_assoc($settingsRes)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$cmsRes = mysqli_query(db(), "SELECT * FROM cms_pages WHERE slug IN ('about','privacy-policy','terms-conditions')");
$cmsPages = [];
while ($row = mysqli_fetch_assoc($cmsRes)) {
    $cmsPages[$row['slug']] = $row;
}

$faqs = mysqli_query(db(), 'SELECT * FROM faqs ORDER BY sort_order');

$pageTitle = 'Site Settings';
$heading = 'Site Settings';
$extraScripts = '<script src="/assets/js/admin-settings.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs-row">
    <button class="tab-btn active" data-tab="general">General</button>
    <button class="tab-btn" data-tab="cms">Pages (About / Privacy / Terms)</button>
    <button class="tab-btn" data-tab="faqs">FAQs</button>
</div>

<div class="settings-panel" id="panel-general">
    <div class="card" style="padding:28px;max-width:640px;" data-reveal>
        <form id="settings-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group"><label class="form-label">Site Name</label><input type="text" class="form-control" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Tagline</label><input type="text" class="form-control" name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Contact Email</label><input type="email" class="form-control" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Contact Phone</label><input type="text" class="form-control" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Contact Address</label><input type="text" class="form-control" name="contact_address" value="<?= e($settings['contact_address'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Currency Symbol</label><input type="text" class="form-control" name="currency_symbol" value="<?= e($settings['currency_symbol'] ?? '$') ?>" style="max-width:100px;"></div>
            <label class="checkbox-row" style="margin-bottom:20px;"><input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>> Maintenance mode</label>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<div class="settings-panel" id="panel-cms" style="display:none;">
    <?php foreach (['about' => 'About Page', 'privacy-policy' => 'Privacy Policy', 'terms-conditions' => 'Terms & Conditions'] as $slug => $label): $page = $cmsPages[$slug] ?? ['title' => $label, 'content' => '', 'meta_title' => '', 'meta_description' => '']; ?>
    <div class="card" style="padding:28px;margin-bottom:20px;" data-reveal>
        <h4 style="margin-bottom:16px;"><?= $label ?></h4>
        <form class="cms-form" data-slug="<?= $slug ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="slug" value="<?= $slug ?>">
            <div class="form-group"><label class="form-label">Page Title</label><input type="text" class="form-control" name="title" value="<?= e($page['title']) ?>"></div>
            <div class="form-group"><label class="form-label">Content (HTML allowed)</label><textarea class="form-control" name="content" rows="6"><?= e($page['content']) ?></textarea></div>
            <div class="grid grid-2">
                <div class="form-group"><label class="form-label">Meta Title</label><input type="text" class="form-control" name="meta_title" value="<?= e($page['meta_title']) ?>"></div>
                <div class="form-group"><label class="form-label">Meta Description</label><input type="text" class="form-control" name="meta_description" value="<?= e($page['meta_description']) ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Page</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div class="settings-panel" id="panel-faqs" style="display:none;">
    <div class="card" style="padding:28px;margin-bottom:20px;" data-reveal>
        <h4 style="margin-bottom:16px;">Add FAQ</h4>
        <form id="faq-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group" data-field="question"><label class="form-label">Question</label><input type="text" class="form-control" name="question" required><div class="form-error"></div></div>
            <div class="form-group"><label class="form-label">Answer</label><textarea class="form-control" name="answer" rows="3" required></textarea></div>
            <button type="submit" class="btn btn-primary btn-sm">Add FAQ</button>
        </form>
    </div>
    <div id="faq-list">
        <?php while ($f = mysqli_fetch_assoc($faqs)): ?>
        <div class="card" style="padding:18px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;" data-faq-id="<?= (int)$f['id'] ?>">
            <div><strong><?= e($f['question']) ?></strong><p style="color:var(--color-text-muted);margin-top:4px;font-size:14px;"><?= e($f['answer']) ?></p></div>
            <button type="button" class="btn-icon btn-delete-faq" style="width:32px;height:32px;flex-shrink:0;"><i class="ri-delete-bin-line"></i></button>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script>
document.querySelectorAll('.tabs-row .tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tabs-row .tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.settings-panel').forEach(function (p) { p.style.display = 'none'; });
        btn.classList.add('active');
        document.getElementById('panel-' + btn.getAttribute('data-tab')).style.display = 'block';
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
