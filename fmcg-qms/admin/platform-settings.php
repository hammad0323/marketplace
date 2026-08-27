<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    foreach (['platform_name','primary_color','support_email','timezone','date_format'] as $key) {
        set_platform_setting($key, post($key, ''));
    }
    set_platform_setting('notif_daily_reminder_enabled', post('notif_daily_reminder_enabled') ? '1' : '0');
    set_platform_setting('notif_missed_submission_enabled', post('notif_missed_submission_enabled') ? '1' : '0');
    log_activity(null, current_user_id(), 'update', 'platform_settings', null, 'Updated platform settings');
    flash_set('success', 'Platform settings saved.');
    redirect(base_url('admin/platform-settings.php'));
}

$pageTitle = 'Platform Settings';
$activeMenu = 'platform-settings';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Platform Settings</h4></div>
<form method="POST" class="qc-card">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label small fw-semibold">Platform Name</label><input class="form-control" name="platform_name" value="<?= out(get_platform_setting('platform_name')) ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Primary Color</label><input type="color" class="form-control form-control-color" name="primary_color" value="<?= out(get_platform_setting('primary_color','#2563EB')) ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">Support Email</label><input type="email" class="form-control" name="support_email" value="<?= out(get_platform_setting('support_email')) ?>"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Timezone</label><input class="form-control" name="timezone" value="<?= out(get_platform_setting('timezone','UTC')) ?>"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Date Format</label><input class="form-control" name="date_format" value="<?= out(get_platform_setting('date_format','d M Y')) ?>"></div>
  </div>
  <hr class="my-4">
  <h3 class="mb-3">Notification Defaults</h3>
  <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="notif_daily_reminder_enabled" <?= get_platform_setting('notif_daily_reminder_enabled','1')==='1'?'checked':'' ?>><label class="form-check-label">Send daily submission reminders</label></div>
  <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="notif_missed_submission_enabled" <?= get_platform_setting('notif_missed_submission_enabled','1')==='1'?'checked':'' ?>><label class="form-check-label">Send missed submission alerts</label></div>
  <button type="submit" class="btn btn-primary">Save Settings</button>
</form>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
