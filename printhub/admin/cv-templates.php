<?php
/** CV templates: labels (English / Urdu), category, colours, preview image, featured, enable / disable. Layouts live in core/cv_templates/. */
require __DIR__ . '/_init.php';
$cats = ['professional' => 'Professional', 'modern' => 'Modern', 'simple' => 'Simple', 'minimal' => 'Minimal', 'student' => 'Student', 'fresher' => 'Fresher', 'experienced' => 'Experienced', 'creative' => 'Creative', 'ats' => 'ATS-friendly', 'executive' => 'Executive', 'corporate' => 'Corporate'];
$layouts = array_map(fn($f) => basename($f, '.php'), glob(PH_ROOT . '/core/cv_templates/*.php') ?: []);
if (is_post()) {
    $a = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($a === 'save') {
        $data = ['template_key' => in_array($_POST['template_key'] ?? '', $layouts, true) ? $_POST['template_key'] : 'professional',
            'name' => mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 80), 'name_ur' => nullable(mb_substr((string) ($_POST['name_ur'] ?? ''), 0, 80)),
            'category' => isset($cats[$_POST['category'] ?? '']) ? $_POST['category'] : 'professional', 'description' => nullable(mb_substr((string) ($_POST['description'] ?? ''), 0, 255)),
            'default_color' => valid_color($_POST['default_color'] ?? null, '#0F766E'), 'supports_photo' => !empty($_POST['supports_photo']) ? 1 : 0, 'ats_friendly' => !empty($_POST['ats_friendly']) ? 1 : 0,
            'is_featured' => !empty($_POST['is_featured']) ? 1 : 0, 'is_active' => !empty($_POST['is_active']) ? 1 : 0, 'sort_order' => (int) ($_POST['sort_order'] ?? 0)];
        if ($data['name'] === '') {
            flash('error', 'Enter a template name.');
            redirect('admin/cv-templates.php');
        }
        if (!empty($_FILES['preview']['name'])) {
            try {
                $data['preview_path'] = media_store_image($_FILES['preview'], 'cv-previews', 900);
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
            }
        }
        if (!empty($_POST['remove_preview'])) {
            $data['preview_path'] = null;
        }
        if ($id) {
            db_update('cv_templates', $data, 'id = ?', [$id]);
        } else {
            $id = db_insert('cv_templates', $data);
        }
        activity('admin', (int) current_admin()['id'], 'cv_template_saved', null, 'cv_template', $id);
        flash('success', 'Template saved.');
    }
    if ($a === 'delete') {
        db_exec('UPDATE cv_templates SET deleted_at = NOW(), is_active = 0 WHERE id = ?', [$id]);
        flash('success', 'Template deleted. Existing CVs keep working.');
    }
    redirect('admin/cv-templates.php');
}
$rows = db_all('SELECT t.*, (SELECT COUNT(*) FROM customer_cvs c WHERE c.template_id = t.id) used FROM cv_templates t WHERE t.deleted_at IS NULL ORDER BY sort_order, id');
$edit = isset($_GET['edit']) ? db_row('SELECT * FROM cv_templates WHERE id = ? AND deleted_at IS NULL', [(int) $_GET['edit']]) : null;
admin_header('CV templates');
?>
<div class="row g-3">
<div class="col-xl-8"><div class="row g-3">
<?php foreach ($rows as $t): ?>
<div class="col-sm-6 col-lg-4"><div class="p-card h-100 mb-0"><div style="aspect-ratio:210/297;overflow:hidden;border-bottom:1px solid #E2E8F0;background:#F1F5F9;position:relative">
    <?php if ($t['preview_path']): ?><img src="<?= e(path('media/' . $t['preview_path'])) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?>
    <iframe src="<?= e(path('cv/template-preview/' . $t['id'])) ?>" title="Preview" loading="lazy" style="width:210mm;height:297mm;border:0;transform:scale(.32);transform-origin:0 0;pointer-events:none;position:absolute"></iframe><?php endif; ?>
    <?php if ((int) $t['is_featured']): ?><span class="badge text-bg-warning position-absolute" style="top:8px;left:8px">Featured</span><?php endif; ?></div>
    <div class="p-card-body py-3"><div class="d-flex align-items-start gap-2"><div><strong><?= e($t['name']) ?></strong><?= $t['name_ur'] ? ' <span dir="rtl" class="text-muted small">' . e($t['name_ur']) . '</span>' : '' ?>
        <div class="small text-muted"><?= e($cats[$t['category']] ?? $t['category']) ?> · <?= (int) $t['used'] ?> CVs<?= (int) $t['ats_friendly'] ? ' · ATS' : '' ?></div></div>
        <span class="ms-auto badge rounded-pill text-bg-<?= (int) $t['is_active'] ? 'success' : 'secondary' ?>"><?= (int) $t['is_active'] ? 'On' : 'Off' ?></span></div>
        <div class="d-flex gap-1 mt-2"><a class="btn btn-sm btn-outline-primary" href="?edit=<?= (int) $t['id'] ?>">Edit</a>
        <form method="post" data-confirm="Delete this template?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></div></div></div></div>
<?php endforeach; ?>
</div></div>
<div class="col-xl-4"><div class="p-card"><div class="p-card-head"><?= $edit ? 'Edit template' : 'Add template' ?><?php if ($edit): ?><a class="ms-auto small" href="<?= e(path('admin/cv-templates.php')) ?>">New</a><?php endif; ?></div><div class="p-card-body">
<?php $t = $edit ?: ['template_key' => 'professional', 'name' => '', 'name_ur' => '', 'category' => 'professional', 'description' => '', 'default_color' => '#0F766E', 'supports_photo' => 1, 'ats_friendly' => 0, 'is_featured' => 0, 'is_active' => 1, 'sort_order' => count($rows) + 1, 'preview_path' => null]; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <?= f_select('template_key', 'Layout', array_combine($layouts, array_map('ucfirst', $layouts)), $t['template_key'], 'Each layout is a file in <code>core/cv_templates/</code>. Several templates can share a layout with different colours and labels.') ?>
    <?= f_input('name', 'English label', $t['name'], 'text', ['required' => true]) ?>
    <?= f_input('name_ur', 'Urdu label', $t['name_ur'], 'text', ['dir' => 'rtl']) ?>
    <?= f_select('category', 'Category', $cats, $t['category']) ?>
    <?= f_textarea('description', 'Description', $t['description'], 2) ?>
    <div class="row"><div class="col-6"><?= f_input('default_color', 'Default colour', $t['default_color'], 'color') ?></div><div class="col-6"><?= f_input('sort_order', 'Order', $t['sort_order'], 'number') ?></div></div>
    <?= f_switch('supports_photo', 'Shows a profile photo', (int) $t['supports_photo']) ?>
    <?= f_switch('ats_friendly', 'ATS-friendly', (int) $t['ats_friendly']) ?>
    <?= f_switch('is_featured', 'Featured', (int) $t['is_featured']) ?>
    <?= f_switch('is_active', 'Enabled', (int) $t['is_active']) ?>
    <div class="mb-3"><label class="form-label">Preview image (optional)</label><input class="form-control" type="file" name="preview" accept="image/png,image/jpeg,image/webp"><div class="form-text">Without an image, a live preview with sample text is shown.</div>
        <?php if ($t['preview_path']): ?><label class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_preview" value="1"> <span class="form-check-label small">Remove image</span></label><?php endif; ?></div>
    <button class="btn btn-primary w-100">Save template</button>
</form></div></div></div>
</div>
<?php admin_footer();
