<?php
require __DIR__ . '/../config/config.php';

$admin = mp_require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    mp_set_setting('site_name', trim($_POST['site_name'] ?? '') ?: 'Marketplace');
    mp_set_setting('site_tagline', trim($_POST['site_tagline'] ?? ''));
    mp_set_setting('contact_email', trim($_POST['contact_email'] ?? ''));
    mp_set_setting('contact_phone', trim($_POST['contact_phone'] ?? ''));
    mp_set_setting('currency_code', trim($_POST['currency_code'] ?? 'USD'));
    mp_set_setting('currency_symbol', trim($_POST['currency_symbol'] ?? '$'));
    mp_set_setting('commission_rate_artisan', (float) ($_POST['commission_rate_artisan'] ?? 0));
    mp_set_setting('commission_rate_business', (float) ($_POST['commission_rate_business'] ?? 0));
    mp_set_setting('social_facebook', trim($_POST['social_facebook'] ?? ''));
    mp_set_setting('social_instagram', trim($_POST['social_instagram'] ?? ''));
    mp_set_setting('social_twitter', trim($_POST['social_twitter'] ?? ''));
    mp_set_setting('footer_about_text', trim($_POST['footer_about_text'] ?? ''));
    mp_set_setting('vendor_registration_enabled', isset($_POST['vendor_registration_enabled']));
    mp_set_setting('maintenance_mode', isset($_POST['maintenance_mode']));

    mp_log_activity('admin', $admin['id'], 'settings.updated');
    mp_flash('success', 'Settings saved.');
    mp_redirect('settings.php');
}

$settings = mp_all_settings();

$pageTitle = 'Settings';
require __DIR__ . '/../templates/admin-header.php';
?>

<h1>Settings</h1>
<p style="color:var(--ink-500); margin-top:-.5rem;">Every value here is read live by the site — no redeploy needed.</p>

<form method="post" action="settings.php">
    <?= mp_csrf_field() ?>

    <div class="admin-panel">
        <h2 style="margin-top:0;">General</h2>
        <div class="form-group">
            <label for="site_name">Site Name</label>
            <input type="text" id="site_name" name="site_name" value="<?= mp_e($settings['site_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="site_tagline">Tagline</label>
            <input type="text" id="site_tagline" name="site_tagline" value="<?= mp_e($settings['site_tagline'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="footer_about_text">Footer About Text</label>
            <textarea id="footer_about_text" name="footer_about_text" rows="3"><?= mp_e($settings['footer_about_text'] ?? '') ?></textarea>
        </div>
    </div>

    <div class="admin-panel">
        <h2 style="margin-top:0;">Contact</h2>
        <div class="checkout-address-grid">
            <div class="form-group">
                <label for="contact_email">Contact Email</label>
                <input type="email" id="contact_email" name="contact_email" value="<?= mp_e($settings['contact_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="contact_phone">Contact Phone</label>
                <input type="text" id="contact_phone" name="contact_phone" value="<?= mp_e($settings['contact_phone'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="admin-panel">
        <h2 style="margin-top:0;">Currency &amp; Commission</h2>
        <div class="checkout-address-grid">
            <div class="form-group">
                <label for="currency_code">Currency Code</label>
                <input type="text" id="currency_code" name="currency_code" value="<?= mp_e($settings['currency_code'] ?? 'USD') ?>" maxlength="3">
            </div>
            <div class="form-group">
                <label for="currency_symbol">Currency Symbol</label>
                <input type="text" id="currency_symbol" name="currency_symbol" value="<?= mp_e($settings['currency_symbol'] ?? '$') ?>" maxlength="5">
            </div>
        </div>
        <div class="checkout-address-grid">
            <div class="form-group">
                <label for="commission_rate_artisan">Artisan Commission (%)</label>
                <input type="number" id="commission_rate_artisan" name="commission_rate_artisan" step="0.1" min="0" max="100" value="<?= mp_e((string) ($settings['commission_rate_artisan'] ?? 0)) ?>">
            </div>
            <div class="form-group">
                <label for="commission_rate_business">Business Commission (%)</label>
                <input type="number" id="commission_rate_business" name="commission_rate_business" step="0.1" min="0" max="100" value="<?= mp_e((string) ($settings['commission_rate_business'] ?? 0)) ?>">
            </div>
        </div>
    </div>

    <div class="admin-panel">
        <h2 style="margin-top:0;">Social Links</h2>
        <div class="form-group">
            <label for="social_facebook">Facebook URL</label>
            <input type="url" id="social_facebook" name="social_facebook" value="<?= mp_e($settings['social_facebook'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="social_instagram">Instagram URL</label>
            <input type="url" id="social_instagram" name="social_instagram" value="<?= mp_e($settings['social_instagram'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="social_twitter">Twitter / X URL</label>
            <input type="url" id="social_twitter" name="social_twitter" value="<?= mp_e($settings['social_twitter'] ?? '') ?>">
        </div>
    </div>

    <div class="admin-panel">
        <h2 style="margin-top:0;">Feature Toggles</h2>
        <div class="checkbox-grid" style="grid-template-columns: 1fr;">
            <label><input type="checkbox" name="vendor_registration_enabled" <?= !empty($settings['vendor_registration_enabled']) ? 'checked' : '' ?>> Allow new vendor registrations</label>
            <label><input type="checkbox" name="maintenance_mode" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>> Maintenance mode</label>
        </div>
    </div>

    <button type="submit" class="btn">Save Settings</button>
</form>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
