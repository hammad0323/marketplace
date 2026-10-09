<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('content.homepage');
if (is_post()) {
    require_csrf();
    switch (input('action')) {
        case 'publish':
            db_exec('UPDATE homepage_sections SET settings = COALESCE(draft_settings, settings), is_enabled = COALESCE(draft_enabled, is_enabled), sort_order = COALESCE(draft_sort, sort_order), draft_settings = NULL, draft_enabled = NULL, draft_sort = NULL');
            audit_log('homepage_published', 'homepage');
            flash('success', 'Homepage changes are now live.');
            break;
        case 'discard':
            db_exec('UPDATE homepage_sections SET draft_settings = NULL, draft_enabled = NULL, draft_sort = NULL');
            audit_log('homepage_drafts_discarded', 'homepage');
            flash('success', 'Draft changes discarded.');
            break;
        case 'announcement':
            $msgs = [];
            foreach (input_array('messages') as $m) {
                if (is_array($m) && trim($m['text'] ?? '') !== '') {
                    $msgs[] = ['text' => mb_substr(trim($m['text']), 0, 160), 'url' => mb_substr(trim($m['url'] ?? ''), 0, 255)];
                }
            }
            save_setting('announcement_messages', $msgs);
            save_setting('announcement_enabled', input_bool('announcement_enabled') ? '1' : '0');
            foreach (['announcement_bg', 'announcement_color'] as $k) {
                save_setting($k, valid_hex(input($k)) ? input($k) : '');
            }
            save_setting('announcement_interval', (string) max(2000, min(20000, input_int('announcement_interval', 5000))));
            audit_log('announcement_updated', 'settings');
            flash('success', 'Announcement bar updated (live).');
            break;
    }
    redirect(admin_url('homepage'));
}
$sections = db_all('SELECT * FROM homepage_sections ORDER BY COALESCE(draft_sort, sort_order), id');
$hasDraft = (bool) db_val('SELECT COUNT(*) FROM homepage_sections WHERE draft_settings IS NOT NULL OR draft_enabled IS NOT NULL OR draft_sort IS NOT NULL');
$msgs = setting_json('announcement_messages', []);
admin_header('Homepage builder', 'homepage');
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
      <div class="me-auto"><strong>Sections</strong><br><small class="text-muted">Drag to reorder, toggle visibility, edit content. Changes are saved as a draft until you publish.</small></div>
      <a class="btn btn-sm btn-outline-primary" href="<?= e(path_url('/', ['preview' => 1])) ?>" target="_blank"><i class="bi bi-eye"></i> Preview draft</a>
      <form method="post" data-confirm="Publish all draft changes to the live homepage?"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><button class="btn btn-sm btn-primary" <?= $hasDraft ? '' : 'disabled' ?>><i class="bi bi-upload"></i> Publish</button></form>
      <form method="post" data-confirm="Discard all unpublished changes?"><?= csrf_field() ?><input type="hidden" name="action" value="discard"><button class="btn btn-sm btn-light" <?= $hasDraft ? '' : 'disabled' ?>>Discard drafts</button></form>
    </div></div>
    <div data-sortable="sections">
      <?php foreach ($sections as $s): $enabled = $s['draft_enabled'] !== null ? (int) $s['draft_enabled'] : (int) $s['is_enabled']; $draft = $s['draft_settings'] !== null || $s['draft_enabled'] !== null || $s['draft_sort'] !== null; ?>
        <div class="section-row<?= $enabled ? '' : ' is-disabled' ?>" data-id="<?= (int) $s['id'] ?>">
          <i class="bi bi-grip-vertical drag-handle"></i>
          <div class="flex-grow-1"><strong><?= e($s['label']) ?></strong> <?= $draft ? '<span class="badge badge-draft">Unpublished changes</span>' : '' ?><br><small class="text-muted"><?= e($s['section_type']) ?></small></div>
          <div class="form-check form-switch mb-0" title="Visible"><input class="form-check-input" type="checkbox" data-toggle-url data-entity="section" data-field="draft_enabled" data-id="<?= (int) $s['id'] ?>" <?= $enabled ? 'checked' : '' ?>></div>
          <?php if ($s['section_type'] === 'hero'): ?><a class="btn btn-sm btn-light" href="<?= e(admin_url('slides')) ?>" title="Slides"><i class="bi bi-images"></i></a><?php endif; ?>
          <a class="btn btn-sm btn-light" href="<?= e(admin_url('section-edit', ['id' => $s['id']])) ?>"><i class="bi bi-pencil"></i> Edit</a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card"><div class="card-header">Announcement bar <small class="text-muted fw-normal">(live immediately)</small></div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="announcement">
        <?= f_check('announcement_enabled', 'Show announcement bar', setting_bool('announcement_enabled', true)) ?>
        <div id="annRows" data-max="6">
          <div class="repeater-rows">
            <?php foreach ($msgs as $i => $m): ?>
              <div class="repeater-row"><div class="d-flex gap-2"><input class="form-control form-control-sm" name="messages[<?= $i ?>][text]" value="<?= e($m['text']) ?>" placeholder="Message" maxlength="160"><button type="button" class="btn btn-sm btn-light" data-repeater-remove><i class="bi bi-x"></i></button></div>
                <input class="form-control form-control-sm mt-1" name="messages[<?= $i ?>][url]" value="<?= e($m['url']) ?>" placeholder="Link (optional), e.g. /shop"></div>
            <?php endforeach; ?>
          </div>
          <template><div class="repeater-row"><div class="d-flex gap-2"><input class="form-control form-control-sm" name="messages[__i__][text]" placeholder="Message" maxlength="160"><button type="button" class="btn btn-sm btn-light" data-repeater-remove><i class="bi bi-x"></i></button></div><input class="form-control form-control-sm mt-1" name="messages[__i__][url]" placeholder="Link (optional)"></div></template>
        </div>
        <button type="button" class="btn btn-sm btn-light mb-3" data-repeater-add="#annRows"><i class="bi bi-plus"></i> Add message</button>
        <div class="row"><div class="col-6"><?= f_color('announcement_bg', 'Background', setting('announcement_bg')) ?></div><div class="col-6"><?= f_color('announcement_color', 'Text colour', setting('announcement_color')) ?></div></div>
        <?= f_number('announcement_interval', 'Rotate every (ms)', setting('announcement_interval', '5000'), ['min' => 2000, 'step' => 500]) ?>
        <?= f_submit('Save announcement') ?>
      </form>
    </div></div>
  </div>
</div>
<?php admin_footer();
