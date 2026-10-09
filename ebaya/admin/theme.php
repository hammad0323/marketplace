<?php
/** Theme settings with draft → preview → publish. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('theme.manage');
if (is_post()) {
    csrf_check();
    $mode = post('mode');
    if ($mode === 'publish_draft') {
        db_exec("UPDATE site_settings SET setting_value = draft_value, draft_value = NULL WHERE setting_group = 'theme' AND draft_value IS NOT NULL");
        audit('theme_publish', 'settings');
        flash('success', 'Theme draft published.');
    } elseif ($mode === 'discard') {
        db_exec("UPDATE site_settings SET draft_value = NULL WHERE setting_group = 'theme'");
        flash('success', 'Theme draft discarded.');
    } elseif ($mode === 'reset') {
        db_exec("DELETE FROM site_settings WHERE setting_group = 'theme'");
        audit('theme_reset', 'settings');
        flash('success', 'Theme reset to the Ebaya defaults.');
    } else {
        settings_group_save('theme', $mode !== 'publish');
        audit($mode === 'publish' ? 'theme_publish' : 'theme_draft', 'settings');
        if ($mode === 'preview') { $_SESSION['preview_mode'] = true; redirect(url('?preview=1')); }
        flash('success', $mode === 'publish' ? 'Theme published.' : 'Theme draft saved. Preview it before publishing.');
    }
    redirect(admin_url('theme'));
}
$hasDraft = (bool)db_val("SELECT COUNT(*) FROM site_settings WHERE setting_group = 'theme' AND draft_value IS NOT NULL");
$admin_title = 'Theme';
require __DIR__ . '/partials/header.php';
?>
<?php if ($hasDraft): ?><div class="alert alert-warning d-flex align-items-center gap-2">You have unpublished theme changes.
  <a class="btn btn-sm btn-outline-dark ms-auto" href="<?= e(url('?preview=1')) ?>" target="_blank">Preview</a>
  <form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-success" name="mode" value="publish_draft">Publish draft</button></form>
  <form method="post"><?= csrf_field() ?><button class="btn btn-sm btn-light" name="mode" value="discard">Discard</button></form></div><?php endif; ?>
<div class="card"><div class="card-body">
  <p class="small text-muted">The defaults reproduce the Ebaya palette (deep olive, sage, warm ivory, champagne, antique gold, espresso). Fonts load from Google Fonts. Animations always respect visitors' “reduce motion” setting.</p>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
    <?= settings_group_form('theme', true) ?>
    <div class="d-flex gap-2 border-top pt-3">
      <button class="btn btn-outline-secondary" name="mode" value="draft">Save draft</button>
      <button class="btn btn-outline-primary" name="mode" value="preview" formtarget="_blank">Save draft & preview</button>
      <button class="btn btn-primary ms-auto" name="mode" value="publish">Publish</button>
    </div>
  </form>
  <form method="post" class="mt-3" data-confirm="Reset all theme settings to the original Ebaya design?"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger" name="mode" value="reset">Reset to defaults</button></form>
</div></div>
<?php require __DIR__ . '/partials/footer.php';
