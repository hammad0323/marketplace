<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    foreach (['platform_name', 'primary_color', 'support_email', 'timezone', 'date_format', 'site_url'] as $key) {
        set_platform_setting($key, post($key, ''));
    }
    set_platform_setting('notif_daily_reminder_enabled', post('notif_daily_reminder_enabled') ? '1' : '0');
    set_platform_setting('notif_missed_submission_enabled', post('notif_missed_submission_enabled') ? '1' : '0');

    if (!empty($_FILES['logo']['name'])) {
        $saved = save_uploaded_file($_FILES['logo'], 'branding', ALLOWED_IMAGE_EXT);
        if ($saved) {
            set_platform_setting('logo', $saved['filename']);
        } else {
            flash_set('warning', 'Logo upload failed - please use JPG, PNG, GIF or WEBP under 10MB.');
        }
    }
    if (!empty($_FILES['favicon']['name'])) {
        $saved = save_uploaded_file($_FILES['favicon'], 'branding', ['png', 'ico', 'jpg', 'jpeg', 'gif', 'webp']);
        if ($saved) {
            set_platform_setting('favicon', $saved['filename']);
        } else {
            flash_set('warning', 'Favicon upload failed - please use PNG, ICO, JPG or GIF under 10MB.');
        }
    }

    log_activity(null, current_user_id(), 'update', 'platform_settings', null, 'Updated platform settings');
    flash_set('success', 'Platform settings saved.');
    redirect(base_url('admin/platform-settings.php'));
}

$pageTitle = 'Platform Settings';
$activeMenu = 'platform-settings';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Platform Settings</h4><p class="text-muted mb-0 small">Rebrand the platform - changes apply everywhere immediately, no code or redeploy needed.</p></div>

<form method="POST" enctype="multipart/form-data" class="qc-card">
  <?= csrf_field() ?>
  <h3 class="mb-3">Branding</h3>
  <div class="row g-3 align-items-end mb-3">
    <div class="col-md-6">
      <label class="form-label small fw-semibold">Logo</label>
      <?php if ($logo = app_logo_url()): ?><div class="mb-2"><img src="<?= out($logo) ?>" alt="Logo" style="height:40px;width:auto;border-radius:6px;"></div><?php endif; ?>
      <input type="file" class="form-control" name="logo" accept="image/*">
      <div class="form-text small">Shown in the sidebar, login page and landing page. Leave blank to keep the current logo.</div>
    </div>
    <div class="col-md-6">
      <label class="form-label small fw-semibold">Favicon</label>
      <?php if ($favicon = app_favicon_url()): ?><div class="mb-2"><img src="<?= out($favicon) ?>" alt="Favicon" style="height:24px;width:24px;border-radius:4px;"></div><?php endif; ?>
      <input type="file" class="form-control" name="favicon" accept="image/*">
      <div class="form-text small">Browser tab icon. Leave blank to keep the current favicon.</div>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small fw-semibold">Platform Name</label><input class="form-control" name="platform_name" value="<?= out(get_platform_setting('platform_name')) ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Primary Color</label>
      <div class="d-flex gap-2"><input type="color" class="form-control form-control-color" name="primary_color" value="<?= out(get_platform_setting('primary_color','#2563EB')) ?>"><span class="form-text small align-self-center">Applied to buttons, links and accents across the entire platform</span></div></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Support Email</label><input type="email" class="form-control" name="support_email" value="<?= out(get_platform_setting('support_email')) ?>"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Timezone</label><input class="form-control" name="timezone" value="<?= out(get_platform_setting('timezone','UTC')) ?>"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Date Format</label><input class="form-control" name="date_format" value="<?= out(get_platform_setting('date_format','d M Y')) ?>"></div>
    <div class="col-md-12">
      <label class="form-label small fw-semibold">Site URL</label>
      <input class="form-control" name="site_url" placeholder="https://www.yourdomain.com/beta" value="<?= out(get_platform_setting('site_url')) ?>">
      <div class="form-text small">
        Used for links inside emails and any link generated outside a browser request (e.g. cron reminders), where the domain can't be auto-detected.
        Leave blank to auto-detect from each request. Pages you browse to always resolve their own CSS/JS/links automatically -
        this app is currently running at <code><?= out(BASE_URL ?: '/ (site root)') ?></code>, detected automatically from your server, no configuration needed.
      </div>
    </div>
  </div>
  <hr class="my-4">
  <h3 class="mb-3">Notification Defaults</h3>
  <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="notif_daily_reminder_enabled" <?= get_platform_setting('notif_daily_reminder_enabled','1')==='1'?'checked':'' ?>><label class="form-check-label">Send daily submission reminders</label></div>
  <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="notif_missed_submission_enabled" <?= get_platform_setting('notif_missed_submission_enabled','1')==='1'?'checked':'' ?>><label class="form-check-label">Send missed submission alerts</label></div>
  <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
