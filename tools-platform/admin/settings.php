<?php
require __DIR__ . '/../includes/config.php';
require_super_admin();

$fields = [
    'site_name', 'site_tagline', 'logo', 'favicon',
    'primary_color', 'secondary_color', 'accent_color', 'default_theme',
    'footer_copyright', 'contact_email', 'support_email', 'social_links',
    'google_analytics_id', 'gsc_verification', 'header_scripts', 'footer_scripts',
    'custom_css', 'maintenance_mode', 'default_disclaimer',
    'currency_api_provider', 'currency_api_key', 'currency_base', 'currency_update_frequency',
    'robots_extra_rules',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    foreach ($fields as $field) {
        if ($field === 'maintenance_mode') {
            tp_save_setting($field, isset($_POST[$field]) ? '1' : '0');
            continue;
        }
        $maxLen = in_array($field, ['header_scripts', 'footer_scripts', 'custom_css'], true) ? 20000 : 2000;
        tp_save_setting($field, tp_sanitize_text($_POST[$field] ?? '', $maxLen));
    }
    tp_regenerate_robots();
    tp_log_activity($_SESSION['admin_id'], 'update_settings');
    tp_flash_set('success', 'Settings saved.');
    header('Location: ' . tp_url('admin/settings.php'));
    exit;
}

if (isset($_GET['regenerate_sitemap'])) {
    $ok = tp_regenerate_sitemap();
    tp_flash_set($ok ? 'success' : 'error', $ok ? 'Sitemap regenerated.' : 'Could not write sitemap.xml — check file permissions.');
    header('Location: ' . tp_url('admin/settings.php'));
    exit;
}

$adminPageTitle = 'Settings';
require __DIR__ . '/includes/admin-header.php';
?>
<form method="post">
  <?= tp_csrf_field() ?>
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-identity">Site Identity</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-theme">Theme</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-analytics">Analytics &amp; Verification</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-code">Custom Code</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-currency">Currency API</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-seo">SEO &amp; Sitemap</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-system">System</a></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="tab-identity">
      <div class="admin-card"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Site Name</label><input type="text" name="site_name" class="form-control" value="<?= e(tp_setting('site_name')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Tagline</label><input type="text" name="site_tagline" class="form-control" value="<?= e(tp_setting('site_tagline')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Logo URL</label><input type="text" name="logo" class="form-control" value="<?= e(tp_setting('logo')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Favicon URL</label><input type="text" name="favicon" class="form-control" value="<?= e(tp_setting('favicon')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-control" value="<?= e(tp_setting('contact_email')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Support Email</label><input type="email" name="support_email" class="form-control" value="<?= e(tp_setting('support_email')) ?>"></div>
        <div class="col-md-12"><label class="form-label">Footer Copyright</label><input type="text" name="footer_copyright" class="form-control" value="<?= e(tp_setting('footer_copyright')) ?>"></div>
        <div class="col-md-12"><label class="form-label">Default Disclaimer</label><textarea name="default_disclaimer" class="form-control" rows="2"><?= e(tp_setting('default_disclaimer')) ?></textarea></div>
      </div></div>
    </div>

    <div class="tab-pane fade" id="tab-theme">
      <div class="admin-card"><div class="row g-3">
        <div class="col-md-4"><label class="form-label">Primary Color</label><input type="color" name="primary_color" class="form-control form-control-color" value="<?= e(tp_setting('primary_color')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Secondary Color</label><input type="color" name="secondary_color" class="form-control form-control-color" value="<?= e(tp_setting('secondary_color')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Accent Color</label><input type="color" name="accent_color" class="form-control form-control-color" value="<?= e(tp_setting('accent_color')) ?>"></div>
        <div class="col-md-4"><label class="form-label">Default Theme</label>
          <select name="default_theme" class="form-select">
            <?php foreach (['system'=>'System','light'=>'Light','dark'=>'Dark'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= tp_setting('default_theme') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select></div>
      </div></div>
    </div>

    <div class="tab-pane fade" id="tab-analytics">
      <div class="admin-card"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Google Analytics Measurement ID</label><input type="text" name="google_analytics_id" class="form-control" value="<?= e(tp_setting('google_analytics_id')) ?>" placeholder="G-XXXXXXX"></div>
        <div class="col-md-6"><label class="form-label">Google Search Console Verification</label><input type="text" name="gsc_verification" class="form-control" value="<?= e(tp_setting('gsc_verification')) ?>"></div>
      </div></div>
    </div>

    <div class="tab-pane fade" id="tab-code">
      <div class="admin-card">
        <div class="alert alert-warning small">Custom code runs on every page exactly as entered. Only paste code you trust — it is not sandboxed.</div>
        <div class="mb-3"><label class="form-label">Header Scripts</label><textarea name="header_scripts" class="form-control" rows="4" style="font-family:monospace;"><?= e(tp_setting('header_scripts')) ?></textarea></div>
        <div class="mb-3"><label class="form-label">Footer Scripts</label><textarea name="footer_scripts" class="form-control" rows="4" style="font-family:monospace;"><?= e(tp_setting('footer_scripts')) ?></textarea></div>
        <div><label class="form-label">Custom CSS</label><textarea name="custom_css" class="form-control" rows="4" style="font-family:monospace;"><?= e(tp_setting('custom_css')) ?></textarea></div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-currency">
      <div class="admin-card"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Currency API Provider</label><input type="text" name="currency_api_provider" class="form-control" value="<?= e(tp_setting('currency_api_provider')) ?>" placeholder="e.g. exchangerate.host"></div>
        <div class="col-md-6"><label class="form-label">API Key</label><input type="text" name="currency_api_key" class="form-control" value="<?= e(tp_setting('currency_api_key')) ?>"></div>
        <div class="col-md-6"><label class="form-label">Base Currency</label><input type="text" name="currency_base" class="form-control" value="<?= e(tp_setting('currency_base')) ?>" maxlength="3"></div>
        <div class="col-md-6"><label class="form-label">Update Frequency</label>
          <select name="currency_update_frequency" class="form-select">
            <?php foreach (['hourly'=>'Hourly','daily'=>'Daily','weekly'=>'Weekly'] as $k=>$v): ?>
              <option value="<?= $k ?>" <?= tp_setting('currency_update_frequency') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select></div>
        <p class="text-muted small">No API key configured yet? The Currency Converter tool falls back to admin-entered static rates and clearly labels them as such — it never claims real-time rates it can't back up.</p>
      </div></div>
    </div>

    <div class="tab-pane fade" id="tab-seo">
      <div class="admin-card">
        <label class="form-label">Extra robots.txt rules</label>
        <textarea name="robots_extra_rules" class="form-control" rows="3" style="font-family:monospace;"><?= e(tp_setting('robots_extra_rules')) ?></textarea>
        <a href="?regenerate_sitemap=1" class="btn btn-outline-dark mt-3">Regenerate Sitemap Now</a>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-system">
      <div class="admin-card">
        <div class="form-check">
          <input type="checkbox" name="maintenance_mode" id="maintenanceMode" class="form-check-input" <?= tp_setting('maintenance_mode') === '1' ? 'checked' : '' ?>>
          <label class="form-check-label" for="maintenanceMode">Maintenance Mode (visitors see a holding page; admins can still browse)</label>
        </div>
      </div>
    </div>
  </div>
  <button class="btn tp-btn-calc mt-3" style="width:auto;">Save Settings</button>
</form>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
