<?php
/** Homepage builder: add, reorder, enable/disable and edit sections. */
require __DIR__ . '/../includes/init.php';
admin_require('homepage');
$types = section_types();
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'add' && isset($types[input('type', '', 'post')])) {
        $t = input('type', '', 'post');
        $id = db_insert('homepage_sections', ['section_type' => $t, 'admin_label' => $types[$t]['label'], 'settings' => '{}',
            'sort_order' => (int) db_val('SELECT COALESCE(MAX(sort_order),0)+10 FROM homepage_sections'), 'status' => 'draft']);
        audit_log('home_section_added', 'homepage_section', $id, ['type' => $t]);
        flash('success', 'Section added as a draft — configure it, preview, then publish.');
        redirect('admin/homepage/section.php?id=' . $id);
    }
    if ($a === 'save') {
        foreach ((array) ($_POST['order'] ?? []) as $sid => $o) {
            $st = $_POST['status'][$sid] ?? 'published';
            db_exec('UPDATE homepage_sections SET sort_order = ?, status = ? WHERE id = ?', [(int) $o, in_array($st, ['published', 'draft', 'hidden'], true) ? $st : 'hidden', (int) $sid]);
        }
        audit_log('home_sections_saved', 'homepage_section');
        flash('success', 'Homepage layout saved.');
    }
    if ($a === 'move') {
        $rows = db_all('SELECT id FROM homepage_sections ORDER BY sort_order, id');
        $ids = array_column($rows, 'id');
        $i = array_search((string) input_int('id'), array_map('strval', $ids), true);
        $j = $i + (input('dir', '', 'post') === 'up' ? -1 : 1);
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $k => $sid) db_exec('UPDATE homepage_sections SET sort_order = ? WHERE id = ?', [($k + 1) * 10, (int) $sid]);
        }
    }
    if ($a === 'duplicate') {
        $s = db_row('SELECT * FROM homepage_sections WHERE id = ?', [input_int('id')]);
        if ($s) {
            unset($s['id'], $s['updated_at']);
            $s['admin_label'] .= ' (copy)';
            $s['status'] = 'draft';
            $s['sort_order'] += 1;
            db_insert('homepage_sections', $s);
        }
    }
    if ($a === 'delete') {
        db_exec('DELETE FROM homepage_sections WHERE id = ?', [input_int('id')]);
        audit_log('home_section_deleted', 'homepage_section', input_int('id'));
        flash('success', 'Section removed.');
    }
    redirect('admin/homepage/');
}
$sections = db_all('SELECT * FROM homepage_sections ORDER BY sort_order, id');
admin_header('Homepage builder');
?>
<div class="a-help mb-3"><b>Published</b> sections are live. <b>Draft</b> sections are only visible in <a href="<?= e(url('?preview=1')) ?>" target="_blank">Preview</a> (signed-in admins) — use drafts to prepare changes before publishing. <b>Hidden</b> sections are kept but not shown anywhere.</div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <select class="form-select" name="type"><?php foreach ($types as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t['label']) ?></option><?php endforeach; ?></select>
        <button class="btn btn-dark text-nowrap"><i class="bi bi-plus-lg"></i> Add section</button></form>
    <a class="btn btn-outline-dark ms-auto" href="<?= e(url('?preview=1')) ?>" target="_blank"><i class="bi bi-eye"></i> Preview homepage (incl. drafts)</a>
    <a class="btn btn-outline-dark" href="<?= e(url('admin/homepage/slides.php')) ?>"><i class="bi bi-images"></i> Hero slides</a>
</div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
<?php foreach ($sections as $i => $s): ?>
    <div class="a-section-row<?= $s['status'] !== 'published' ? ' is-hidden' : '' ?>">
        <span class="a-handle"><i class="bi bi-grip-vertical"></i></span>
        <div class="flex-grow-1"><strong><?= e($s['admin_label']) ?></strong><div class="small text-muted"><?= e($types[$s['section_type']]['label'] ?? $s['section_type']) ?></div></div>
        <input class="form-control form-control-sm" style="width:80px" type="number" name="order[<?= (int) $s['id'] ?>]" value="<?= (int) $s['sort_order'] ?>" title="Order">
        <select class="form-select form-select-sm" style="width:130px" name="status[<?= (int) $s['id'] ?>]"><?php foreach (['published' => 'Published', 'draft' => 'Draft', 'hidden' => 'Hidden'] as $k => $l): ?><option value="<?= $k ?>"<?= $s['status'] === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm btn-light" form="up<?= (int) $s['id'] ?>" title="Move up"<?= $i === 0 ? ' disabled' : '' ?>><i class="bi bi-arrow-up"></i></button>
        <button class="btn btn-sm btn-light" form="dn<?= (int) $s['id'] ?>" title="Move down"<?= $i === count($sections) - 1 ? ' disabled' : '' ?>><i class="bi bi-arrow-down"></i></button>
        <a class="btn btn-sm btn-dark" href="<?= e(url('admin/homepage/section.php?id=' . $s['id'])) ?>"><i class="bi bi-pencil"></i> Edit</a>
        <button class="btn btn-sm btn-light" form="dup<?= (int) $s['id'] ?>" title="Duplicate"><i class="bi bi-copy"></i></button>
        <button class="btn btn-sm btn-light text-danger" form="del<?= (int) $s['id'] ?>" title="Delete"><i class="bi bi-trash"></i></button>
    </div>
<?php endforeach; ?>
<?= a_save_bar('', 'Save order & visibility') ?>
</form>
<?php foreach ($sections as $s): $sid = (int) $s['id']; ?>
<form id="up<?= $sid ?>" method="post" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="up"><input type="hidden" name="id" value="<?= $sid ?>"></form>
<form id="dn<?= $sid ?>" method="post" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="move"><input type="hidden" name="dir" value="down"><input type="hidden" name="id" value="<?= $sid ?>"></form>
<form id="dup<?= $sid ?>" method="post" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= $sid ?>"></form>
<form id="del<?= $sid ?>" method="post" class="d-none" data-confirm="Remove “<?= e($s['admin_label']) ?>” from the homepage?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $sid ?>"></form>
<?php endforeach; ?>
<?php admin_footer();
