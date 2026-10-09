<?php
/** Homepage builder: order, visibility, per-section settings with draft → preview → publish. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('homepage.manage');
$schema = homepage_schema();

if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'reorder') {
        foreach (array_values((array)($_POST['ids'] ?? [])) as $i => $sid) db_exec('UPDATE homepage_sections SET sort_order = ? WHERE id = ?', [$i + 1, (int)$sid]);
        audit('homepage_reorder', 'homepage');
        json_out(['ok' => true]);
    }
    if ($act === 'toggle') {
        db_exec('UPDATE homepage_sections SET is_visible = 1 - is_visible WHERE id = ?', [(int)post('id')]);
        audit('homepage_toggle', 'homepage_section', (int)post('id'));
        redirect(admin_url('homepage'));
    }
    if ($act === 'publish_all') {
        db_exec('UPDATE homepage_sections SET settings = draft_settings, draft_settings = NULL WHERE draft_settings IS NOT NULL');
        audit('homepage_publish_all', 'homepage');
        flash('success', 'All homepage drafts published.');
        redirect(admin_url('homepage'));
    }
    if ($act === 'discard_all') {
        db_exec('UPDATE homepage_sections SET draft_settings = NULL');
        flash('success', 'Drafts discarded.');
        redirect(admin_url('homepage'));
    }
    if ($act === 'save') {
        $sec = db_one('SELECT * FROM homepage_sections WHERE id = ?', [(int)post('id')]);
        if ($sec && isset($schema[$sec['section_key']])) {
            try {
                $current = homepage_settings_merge($sec['section_key'], json_decode((string)($sec['draft_settings'] ?? $sec['settings']), true) ?: []);
                $new = homepage_settings_from_post($sec['section_key'], $current);
                $json = json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (post('mode') === 'publish') {
                    db_exec('UPDATE homepage_sections SET settings = ?, draft_settings = NULL WHERE id = ?', [$json, (int)$sec['id']]);
                } else {
                    db_exec('UPDATE homepage_sections SET draft_settings = ? WHERE id = ?', [$json, (int)$sec['id']]);
                }
                // Hand-picked items (products / categories / collections)
                if ($type = $schema[$sec['section_key']][2]) {
                    db_exec('DELETE FROM homepage_section_items WHERE section_id = ?', [(int)$sec['id']]);
                    foreach (array_values(array_unique(array_map('intval', (array)($_POST['items'] ?? [])))) as $i => $iid) {
                        if ($iid) db_exec('INSERT INTO homepage_section_items (section_id, item_type, item_id, sort_order) VALUES (?, ?, ?, ?)', [(int)$sec['id'], $type, $iid, $i]);
                    }
                }
                audit('homepage_section_' . (post('mode') === 'publish' ? 'publish' : 'draft'), 'homepage_section', (int)$sec['id'], ['key' => $sec['section_key']]);
                if (post('mode') === 'preview') {
                    $_SESSION['preview_mode'] = true;
                    redirect(url('?preview=1#'));
                }
                flash('success', post('mode') === 'publish' ? 'Section published.' : 'Draft saved — use Preview to review before publishing.');
            } catch (RuntimeException $e) {
                flash('danger', $e->getMessage());
            }
            redirect(admin_url('homepage?edit=' . $sec['section_key']));
        }
    }
}

$sections = db_all('SELECT * FROM homepage_sections ORDER BY sort_order, id');
$hasDrafts = (bool)array_filter($sections, fn($s) => $s['draft_settings'] !== null);
$editKey = get('edit');
$edit = null;
foreach ($sections as $s) if ($s['section_key'] === $editKey) $edit = $s;

$admin_title = 'Homepage builder';
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
  <span class="small text-muted">Drag sections to reorder. The announcement bar and navigation live at the top of every page (Store settings → Announcement, and Navigation); the footer is at the bottom.</span>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('?preview=1')) ?>" target="_blank"><i class="bi bi-eye"></i> Preview drafts</a>
    <?php if ($hasDrafts): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="publish_all"><button class="btn btn-sm btn-success">Publish all drafts</button></form>
      <form method="post" data-confirm="Discard all unpublished changes?"><?= csrf_field() ?><input type="hidden" name="action" value="discard_all"><button class="btn btn-sm btn-light">Discard drafts</button></form>
    <?php endif; ?>
  </div>
</div>
<div class="row g-3">
  <div class="col-xl-4">
    <div data-sortable="<?= e(admin_url('homepage')) ?>">
      <?php foreach ($sections as $i => $s): ?>
        <div class="section-row<?= $editKey === $s['section_key'] ? ' border-primary' : '' ?>" data-id="<?= (int)$s['id'] ?>">
          <i class="bi bi-grip-vertical drag-handle"></i>
          <div class="flex-grow-1"><strong><?= e($schema[$s['section_key']][0] ?? $s['label']) ?></strong>
            <div class="small"><?= $s['is_visible'] ? '<span class="text-success">Visible</span>' : '<span class="text-muted">Hidden</span>' ?><?= $s['draft_settings'] !== null ? ' · <span class="text-warning">Unpublished draft</span>' : '' ?></div></div>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light" title="<?= $s['is_visible'] ? 'Hide' : 'Show' ?>"><i class="bi bi-eye<?= $s['is_visible'] ? '' : '-slash' ?>"></i></button></form>
          <a class="btn btn-sm btn-light" href="?edit=<?= e($s['section_key']) ?>"><i class="bi bi-pencil"></i></a>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="small text-muted mt-2"><?= count($sections) ?> configurable sections + announcement/navigation + footer = the full homepage.</div>
  </div>
  <div class="col-xl-8">
  <?php if ($edit): $key = $edit['section_key']; [$label, $fields, $itemType] = $schema[$key];
    $vals = homepage_settings_merge($key, json_decode((string)($edit['draft_settings'] ?? $edit['settings']), true) ?: []);
    $groups = [];
    foreach ($fields as $k => $f) $groups[$f[3]][$k] = $f; ?>
    <div class="card"><div class="card-header d-flex"><?= e($label) ?><?php if ($edit['draft_settings'] !== null): ?><span class="badge text-bg-warning ms-2">Editing draft</span><?php endif; ?></div><div class="card-body">
      <?php if ($key === 'hero'): ?><div class="alert alert-light border small">Slide images, text, buttons, colours, overlay, heights and positioning are managed per slide in <a href="<?= e(admin_url('slides')) ?>">Hero slides</a>.</div><?php endif; ?>
      <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <?php foreach ($groups as $g => $gf): ?>
          <h6 class="mt-2 text-uppercase small text-muted"><?= e($g) ?></h6><div class="row">
          <?php foreach ($gf as $k => $f): [$fl, $type, $def] = $f; $v = $vals[$k]; $n = 's[' . $k . ']'; ?>
            <div class="<?= in_array($type, ['textarea', 'image'], true) ? 'col-md-12' : 'col-md-6' ?>">
            <?php if ($type === 'toggle'): ?><?= f_toggle($n, $fl, $v) ?>
            <?php elseif ($type === 'select'): ?><?= f_select($n, $fl, $f[4], $v) ?>
            <?php elseif ($type === 'image'): ?><?= f_image('img_' . $k, $fl, $v ?: null, ['remove' => 'remove[' . $k . ']']) ?>
            <?php elseif ($type === 'textarea'): ?><?= f_text($n, $fl, $v, ['type' => 'textarea', 'rows' => 4]) ?>
            <?php elseif ($type === 'color'): ?><?= f_text($n, $fl, $v, ['type' => 'color']) ?>
            <?php elseif ($type === 'number'): ?><?= f_text($n, $fl, $v, ['type' => 'number', 'min' => 0]) ?>
            <?php else: ?><?= f_text($n, $fl, $v) ?><?php endif; ?>
            </div>
          <?php endforeach; ?></div>
        <?php endforeach; ?>
        <?php if ($itemType):
          $opts = match ($itemType) {
            'product' => array_column(db_all("SELECT id, name FROM products WHERE status = 'published' ORDER BY name"), 'name', 'id'),
            'category' => categories_options(true),
            'collection' => array_column(db_all("SELECT id, name FROM collections WHERE status = 'active' ORDER BY sort_order"), 'name', 'id'),
          }; ?>
          <h6 class="mt-2 text-uppercase small text-muted">Hand-picked <?= e($itemType) ?>s (used when the source is “Hand-picked”)</h6>
          <?= f_select('items', 'Select in display order', $opts, homepage_section_item_ids((int)$edit['id'], $itemType), ['multiple' => true, 'size' => 10]) ?>
        <?php endif; ?>
        <div class="d-flex gap-2 border-top pt-3">
          <button class="btn btn-outline-secondary" name="mode" value="draft">Save draft</button>
          <button class="btn btn-outline-primary" name="mode" value="preview" formtarget="_blank">Save draft & preview</button>
          <button class="btn btn-primary ms-auto" name="mode" value="publish">Publish</button>
        </div>
      </form>
    </div></div>
  <?php else: ?><div class="card"><div class="card-body text-muted">Choose a section to edit its headings, content, images, buttons, colours, spacing, width and animation.</div></div><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
