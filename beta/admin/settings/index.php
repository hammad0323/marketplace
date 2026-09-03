<?php
require __DIR__ . '/../../config/config.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $fields = ['site_name','site_description','site_phone','site_email','site_address','currency_code','currency_symbol',
        'timezone','footer_text','copyright_text','primary_color','secondary_color','accent_color','button_color',
        'header_color','footer_color','background_color','text_color','employee_limit','commission_due_days',
        'google_client_id','google_client_secret'];
    foreach ($fields as $f) if (isset($_POST[$f])) set_setting($f, trim($_POST[$f]));
    set_setting('maintenance_mode', isset($_POST['maintenance_mode']) ? '1' : '0');

    $logoUpload = handle_image_upload('site_logo', 'banners');
    if (!empty($logoUpload['path'])) set_setting('site_logo', $logoUpload['path']);
    $faviconUpload = handle_image_upload('site_favicon', 'banners');
    if (!empty($faviconUpload['path'])) set_setting('site_favicon', $faviconUpload['path']);

    audit_log('admin', current_admin()['id'], current_admin()['name'], 'Updated settings', 'settings');
    flash('success', 'Settings saved successfully.');
    redirect(admin_url('settings/index.php'));
}

$dashRole = 'admin'; $pageTitle = 'Settings'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Site Settings</h1></div>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="dash-form-card">
    <h3>General</h3>
    <div class="form-row">
      <div><label>Site Name</label><input type="text" name="site_name" value="<?= clean(get_setting('site_name')) ?>"></div>
      <div><label>Currency Code</label><input type="text" name="currency_code" value="<?= clean(get_setting('currency_code')) ?>"></div>
    </div>
    <label>Site Description</label><textarea name="site_description" rows="2"><?= clean(get_setting('site_description')) ?></textarea>
    <div class="form-row">
      <div><label>Phone</label><input type="text" name="site_phone" value="<?= clean(get_setting('site_phone')) ?>"></div>
      <div><label>Email</label><input type="text" name="site_email" value="<?= clean(get_setting('site_email')) ?>"></div>
    </div>
    <label>Address</label><input type="text" name="site_address" value="<?= clean(get_setting('site_address')) ?>">
    <div class="form-row">
      <div><label>Currency Symbol</label><input type="text" name="currency_symbol" value="<?= clean(get_setting('currency_symbol')) ?>"></div>
      <div><label>Timezone</label><input type="text" name="timezone" value="<?= clean(get_setting('timezone')) ?>"></div>
    </div>
    <label>Footer Text</label><input type="text" name="footer_text" value="<?= clean(get_setting('footer_text')) ?>">
    <label>Copyright Text</label><input type="text" name="copyright_text" value="<?= clean(get_setting('copyright_text')) ?>">
    <div class="form-row">
      <div><label>Site Logo</label><input type="file" name="site_logo"></div>
      <div><label>Favicon</label><input type="file" name="site_favicon"></div>
    </div>
  </div>

  <div class="dash-form-card">
    <h3>Theme Colors</h3>
    <div class="form-row">
      <?php foreach (['primary_color'=>'Primary','secondary_color'=>'Secondary','accent_color'=>'Accent','button_color'=>'Button'] as $k=>$l): ?>
        <div><label><?= $l ?></label><input type="color" name="<?= $k ?>" value="<?= clean(get_setting($k)) ?>"></div>
      <?php endforeach; ?>
    </div>
    <div class="form-row">
      <?php foreach (['header_color'=>'Header','footer_color'=>'Footer','background_color'=>'Background','text_color'=>'Text'] as $k=>$l): ?>
        <div><label><?= $l ?></label><input type="color" name="<?= $k ?>" value="<?= clean(get_setting($k)) ?>"></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="dash-form-card">
    <h3>Marketplace Rules</h3>
    <div class="form-row">
      <div><label>Employee Limit Per Shop</label><input type="number" name="employee_limit" value="<?= clean(get_setting('employee_limit')) ?>"></div>
      <div><label>Commission Due (Days)</label><input type="number" name="commission_due_days" value="<?= clean(get_setting('commission_due_days')) ?>"></div>
    </div>
    <label class="switch-label"><input type="checkbox" name="maintenance_mode" <?= get_setting('maintenance_mode') === '1' ? 'checked' : '' ?>> Maintenance Mode</label>
  </div>

  <div class="dash-form-card">
    <h3>Google OAuth (Customer Login)</h3>
    <div class="form-row">
      <div><label>Google Client ID</label><input type="text" name="google_client_id" value="<?= clean(get_setting('google_client_id')) ?>"></div>
      <div><label>Google Client Secret</label><input type="password" name="google_client_secret" value="<?= clean(get_setting('google_client_secret')) ?>"></div>
    </div>
    <p class="text-muted">Redirect URI to whitelist in Google Console: <code><?= base_url('actions/auth.php?do=google_callback') ?></code></p>
  </div>

  <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
