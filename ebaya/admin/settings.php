<?php
/** Store settings: general/branding, announcement, social, checkout & policies, email. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin();
$groups = array_filter(['general', 'announcement', 'social', 'checkout', 'email'], fn($g) => can(settings_schema()[$g][1]));
if (!$groups) require_admin('settings.manage');
$tab = in_list(get('tab'), array_values($groups), reset($groups));

if (is_post()) {
    csrf_check();
    $g = post('group');
    if (!in_array($g, $groups, true)) { http_response_code(403); exit('Forbidden'); }
    try {
        settings_group_save($g);
        audit('settings_update', 'settings', null, ['group' => $g]);
        flash('success', 'Settings saved.');
    } catch (RuntimeException $e) { flash('danger', $e->getMessage()); }
    if ($g === 'email' && post('test_to') && v_email(post('test_to'))) {
        settings_all(true);
        $ok = send_mail(post('test_to'), 'Ebaya test email', '<p>Your email settings are working.</p>');
        flash($ok ? 'success' : 'warning', $ok ? 'Test email sent.' : 'Test email failed or sending is disabled — see the email log below.');
    }
    redirect(admin_url('settings?tab=' . $g));
}
$admin_title = 'Store settings';
require __DIR__ . '/partials/header.php';
?>
<ul class="nav nav-tabs mb-3"><?php foreach ($groups as $g): ?><li class="nav-item"><a class="nav-link<?= $g === $tab ? ' active' : '' ?>" href="?tab=<?= $g ?>"><?= e(settings_schema()[$g][0]) ?></a></li><?php endforeach; ?></ul>
<div class="card"><div class="card-body">
  <form method="post" enctype="multipart/form-data" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="group" value="<?= e($tab) ?>">
    <?= settings_group_form($tab) ?>
    <?php if ($tab === 'email'): ?><div class="row"><div class="col-md-6"><?= f_text('test_to', 'Send a test email to (optional)', '', ['type' => 'email']) ?></div></div><?php endif; ?>
    <button class="btn btn-primary">Save settings</button>
  </form>
</div></div>
<?php if ($tab === 'email'): $log = db_all('SELECT * FROM email_log ORDER BY id DESC LIMIT 15'); ?>
<div class="card mt-3"><div class="card-header">Recent email log</div><table class="table table-sm mb-0"><tbody>
  <?php foreach ($log as $l): ?><tr><td class="small"><?= e($l['created_at']) ?></td><td class="small"><?= e($l['recipient']) ?></td><td class="small"><?= e($l['subject']) ?></td><td><?= e($l['status']) ?></td><td class="small text-muted"><?= e($l['error']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php';
