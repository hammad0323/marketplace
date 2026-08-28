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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'test_email') {
    csrf_require();
    $testTo = post('test_email');
    if (!validate_email($testTo)) {
        flash_set('danger', 'Enter a valid email address to send the test to.');
    } else {
        $err = send_test_email($testTo);
        if ($err === null) {
            log_activity(null, current_user_id(), 'update', 'email_settings', null, "Sent test email to $testTo");
            flash_set('success', "Test email sent to $testTo. Check the inbox (and spam folder).");
        } else {
            flash_set('danger', "Test email failed: $err");
        }
    }
    redirect(base_url('admin/email-settings.php'));
}

$templates = db_all("SELECT * FROM email_templates WHERE company_id IS NULL ORDER BY name", []);
$recentEmailLogs = db_all("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 15", []);

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

<div class="qc-card mb-4">
  <h3 class="mb-2">Send Test Email</h3>
  <p class="small text-muted">Verify your SMTP configuration actually delivers before relying on it. If no SMTP host is set above, this falls back to PHP's <code>mail()</code>.</p>
  <form method="POST" class="d-flex gap-2" style="max-width:480px;">
    <?= csrf_field() ?><input type="hidden" name="form" value="test_email">
    <input type="email" class="form-control" name="test_email" placeholder="you@example.com" required>
    <button type="submit" class="btn btn-soft-primary text-nowrap"><i class="bi bi-send"></i> Send Test</button>
  </form>
</div>

<div class="qc-card mb-4">
  <h3 class="mb-3">Recent Email Activity</h3>
  <table class="table table-sm mb-0">
    <thead><tr><th>Time</th><th>To</th><th>Subject</th><th>Event</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($recentEmailLogs as $l): ?>
      <tr><td class="small text-muted"><?= fmt_datetime($l['created_at']) ?></td><td class="small"><?= out($l['to_email']) ?></td>
        <td class="small"><?= out($l['subject']) ?></td><td class="small text-capitalize"><?= out(str_replace('_',' ',(string)$l['event_key'])) ?></td>
        <td><?= status_badge($l['status']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recentEmailLogs): ?><tr><td colspan="5" class="text-center text-muted py-3">No emails sent yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
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
