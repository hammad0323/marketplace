<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'smtp') {
    csrf_require();
    foreach (['smtp_host','smtp_port','smtp_username','smtp_password','smtp_secure','smtp_from_email','smtp_from_name'] as $key) {
        set_platform_setting($key, post_raw($key, ''));
    }
    log_activity(null, current_user_id(), 'update', 'email_settings', null, 'Updated SMTP configuration');
    flash_set('success', 'Email delivery settings saved.');
    redirect(base_url('admin/email-settings.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'template') {
    csrf_require();
    $id = post_int('template_id');
    db_exec("UPDATE email_templates SET subject=?, body_html=? WHERE id=? AND company_id IS NULL", [post('subject'), post_raw('body_html'), $id]);
    log_activity(null, current_user_id(), 'update', 'email_template', $id, 'Updated email template');
    flash_set('success', 'Template updated.');
    redirect(base_url('admin/email-settings.php'));
}

$templates = db_all("SELECT * FROM email_templates WHERE company_id IS NULL ORDER BY name", []);

$pageTitle = 'Email Settings';
$activeMenu = 'email-settings';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Email Settings</h4><p class="text-muted mb-0 small">SMTP delivery and dynamic notification templates</p></div>

<div class="qc-card mb-4">
  <h3 class="mb-3">SMTP Configuration</h3>
  <form method="POST" class="row g-3">
    <?= csrf_field() ?><input type="hidden" name="form" value="smtp">
    <div class="col-md-6"><label class="form-label small fw-semibold">SMTP Host</label><input class="form-control" name="smtp_host" value="<?= out(get_platform_setting('smtp_host')) ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Port</label><input class="form-control" name="smtp_port" value="<?= out(get_platform_setting('smtp_port','587')) ?>"></div>
    <div class="col-md-4"><label class="form-label small fw-semibold">Encryption</label>
      <select class="form-select" name="smtp_secure">
        <?php foreach (['tls'=>'TLS','ssl'=>'SSL','none'=>'None'] as $k=>$v): ?><option value="<?= $k ?>" <?= get_platform_setting('smtp_secure','tls')===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6"><label class="form-label small fw-semibold">SMTP Username</label><input class="form-control" name="smtp_username" value="<?= out(get_platform_setting('smtp_username')) ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">SMTP Password</label><input type="password" class="form-control" name="smtp_password" value="<?= out(get_platform_setting('smtp_password')) ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">From Email</label><input class="form-control" name="smtp_from_email" value="<?= out(get_platform_setting('smtp_from_email')) ?>"></div>
    <div class="col-md-6"><label class="form-label small fw-semibold">From Name</label><input class="form-control" name="smtp_from_name" value="<?= out(get_platform_setting('smtp_from_name')) ?>"></div>
    <div class="col-12"><button type="submit" class="btn btn-primary">Save SMTP Settings</button></div>
  </form>
</div>

<div class="qc-card">
  <h3 class="mb-3">Email Templates</h3>
  <div class="accordion" id="tplAccordion">
    <?php foreach ($templates as $i => $t): ?>
    <div class="accordion-item">
      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tpl<?= $t['id'] ?>">
        <?= out($t['name']) ?> <span class="badge bg-secondary-subtle text-secondary ms-2"><?= out($t['event_key']) ?></span></button></h2>
      <div id="tpl<?= $t['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#tplAccordion">
        <div class="accordion-body">
          <form method="POST">
            <?= csrf_field() ?><input type="hidden" name="form" value="template"><input type="hidden" name="template_id" value="<?= $t['id'] ?>">
            <div class="mb-2"><label class="form-label small fw-semibold">Subject</label><input class="form-control" name="subject" value="<?= out($t['subject']) ?>"></div>
            <div class="mb-2"><label class="form-label small fw-semibold">Body (HTML, supports {{variables}})</label><textarea class="form-control" name="body_html" rows="5"><?= out($t['body_html']) ?></textarea></div>
            <button type="submit" class="btn btn-sm btn-soft-primary">Save Template</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
