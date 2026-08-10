<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

$editId = (int) ($_GET['edit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        $t = db_select_one($conn, 'SELECT is_active FROM email_templates WHERE id = ?', [$id]);
        if ($t) {
            db_execute($conn, 'UPDATE email_templates SET is_active = ? WHERE id = ?', [$t['is_active'] ? 0 : 1, $id]);
        }
        redirect('/admin/emails.php');
    } elseif ($action === 'save') {
        db_execute(
            $conn,
            'UPDATE email_templates SET subject = ?, body_html = ? WHERE id = ?',
            [clean_input($_POST['subject'] ?? ''), $_POST['body_html'] ?? '', $id]
        );
        log_audit($conn, (int) $admin['id'], 'email_template', $id, 'update');
        flash_set('success', 'Template updated.');
        redirect('/admin/emails.php');
    }
}

$templates = db_select($conn, 'SELECT * FROM email_templates ORDER BY template_key');
$editingTemplate = $editId ? db_select_one($conn, 'SELECT * FROM email_templates WHERE id = ?', [$editId]) : null;
$recentLog = db_select($conn, 'SELECT * FROM email_log ORDER BY created_at DESC LIMIT 20');

$adminPageTitle = 'Email Templates';
$adminActive = 'emails';
require __DIR__ . '/_layout_top.php';
?>

<?php if ($editingTemplate): ?>
  <a href="/admin/emails.php" style="color:var(--ink-mute);font-size:13.5px;"><i class="bi bi-arrow-left"></i> Back to templates</a>
  <div class="panel" style="margin-top:16px;">
    <div class="panel-head"><h3>Edit: <?php echo e($editingTemplate['template_key']); ?></h3></div>
    <form method="post" class="form-w">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?php echo (int) $editingTemplate['id']; ?>">
      <label>Subject</label>
      <input type="text" name="subject" value="<?php echo e($editingTemplate['subject']); ?>">
      <label>Body (HTML, use {placeholders})</label>
      <textarea name="body_html" rows="8"><?php echo e($editingTemplate['body_html']); ?></textarea>
      <div class="form-hint">Available placeholders depend on the trigger — check the docs, but common ones are {name}, {site_name}, {business_name}, {booking_ref}, {service_title}, {status}.</div>
      <button type="submit" class="btn-w btn-primary" style="margin-top:16px;">Save template</button>
    </form>
  </div>
<?php else: ?>
  <div class="panel">
    <div class="panel-head"><h3>Templates</h3></div>
    <table class="table-w">
      <thead><tr><th>Key</th><th>Subject</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($templates as $t): ?>
          <tr>
            <td><code><?php echo e($t['template_key']); ?></code></td>
            <td><?php echo e($t['subject']); ?></td>
            <td><?php echo status_badge($t['is_active'] ? 'active' : 'blocked'); ?></td>
            <td style="text-align:right;">
              <a href="?edit=<?php echo (int) $t['id']; ?>" class="btn-w btn-outline btn-sm">Edit</a>
              <form method="post" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
                <button type="submit" class="btn-w btn-outline btn-sm"><?php echo $t['is_active'] ? 'Disable' : 'Enable'; ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-head"><h3>Recent send log</h3></div>
    <?php if ($recentLog): ?>
      <table class="table-w">
        <thead><tr><th>To</th><th>Subject</th><th>Status</th><th>When</th></tr></thead>
        <tbody>
          <?php foreach ($recentLog as $l): ?>
            <tr>
              <td><?php echo e($l['to_email']); ?></td>
              <td><?php echo e($l['subject']); ?></td>
              <td><?php echo status_badge($l['status'] === 'sent' ? 'approved' : ($l['status'] === 'failed' ? 'rejected' : 'pending')); ?></td>
              <td><?php echo e(time_ago($l['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="empty-state" style="padding:24px;"><div class="icon-wrap"><i class="bi bi-envelope"></i></div><h4>No emails sent yet</h4><p>Configure SMTP in Settings to actually deliver — until then, triggered emails are logged here.</p></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
