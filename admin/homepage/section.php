<?php
/** Generic section editor generated from the section's field definitions (includes/sections.php). */
require __DIR__ . '/../includes/init.php';
admin_require('homepage');
$section = db_row('SELECT * FROM homepage_sections WHERE id = ?', [input_int('id')]);
if (!$section) {
    flash('error', 'Section not found.');
    redirect('admin/homepage/');
}
$type = section_types()[$section['section_type']] ?? null;
$settings = section_settings($section);
if (is_post()) {
    try {
        $clean = section_sanitize($section['section_type'], $_POST['s'] ?? [], $settings);
        // Image fields: section_sanitize looks for "<key>_file" uploads; map nested names
        db_update('homepage_sections', [
            'admin_label' => mb_substr(input('admin_label', $section['admin_label'], 'post'), 0, 120) ?: $section['admin_label'],
            'settings' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => in_array(input('status', '', 'post'), ['published', 'draft', 'hidden'], true) ? input('status', '', 'post') : $section['status'],
        ], 'id = ?', [(int) $section['id']]);
        audit_log('home_section_saved', 'homepage_section', (int) $section['id'], ['type' => $section['section_type']]);
        flash('success', 'Section saved.');
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect('admin/homepage/section.php?id=' . $section['id']);
}
$hasHtml = (bool) array_filter(section_fields($section['section_type']), fn($f) => $f['type'] === 'html');
admin_header('Edit section: ' . $section['admin_label'], ['editor' => $hasHtml]);

/** Renders one field. Uploads use the top-level name "<key>_file" (read by section_sanitize). */
function section_field(string $key, array $f, $value): string
{
    $name = 's[' . $key . ']';
    $help = $f['help'] ?? '';
    switch ($f['type']) {
        case 'textarea': return a_textarea($name, $f['label'], $value, 3, [], $help);
        case 'html': return a_html($name, $f['label'], $value, $help);
        case 'number': return a_input($name, $f['label'], $value, 'number', [], $help);
        case 'url': return a_input($name, $f['label'], $value, 'text', ['placeholder' => 'e.g. shop, collections/smart-casual or https://…'], $help);
        case 'color': return a_color($name, $f['label'], $value, 'Leave empty to use the theme default.');
        case 'checkbox': return a_check($name, $f['label'], $value === '1', $help);
        case 'select': return a_select($name, $f['label'], $f['options'], $value, [], $help);
        case 'products':
        case 'collections':
        case 'categories': return a_picker('s[' . $key . ']', $f['label'], $f['type'], (array) $value, $help);
        case 'image':
            $html = a_image($name, $f['label'], $value ?: null, $help);
            // a_image() names the upload "s[key]_file"; section_sanitize expects "<key>_file"
            return str_replace(['name="s[' . $key . ']_file"', 'name="s[' . $key . ']_remove"'], ['name="' . $key . '_file"', 'name="s[' . $key . '_remove]"'], $html);
        default: return a_input($name, $f['label'], $value, 'text', [], $help);
    }
}
?>
<?php if (!empty($type['note'])): ?><div class="a-help mb-3"><?= e($type['note']) ?><?php if ($section['section_type'] === 'hero'): ?> <a href="<?= e(url('admin/homepage/slides.php')) ?>">Manage slides →</a><?php endif; ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-4"><div class="col-lg-8">
    <div class="card a-card"><div class="card-header">Content</div><div class="card-body">
        <?php foreach ($type['fields'] as $key => $f) echo section_field($key, $f, $settings[$key] ?? ''); ?>
    </div></div>
</div><div class="col-lg-4">
    <div class="card a-card"><div class="card-header">Section</div><div class="card-body">
        <?= a_input('admin_label', 'Admin label', $section['admin_label']) ?>
        <?= a_select('status', 'Status', ['published' => 'Published (live)', 'draft' => 'Draft (preview only)', 'hidden' => 'Hidden'], $section['status']) ?>
        <button class="btn btn-dark w-100 mb-2">Save section</button>
        <a class="btn btn-outline-dark w-100" href="<?= e(url('?preview=1#section-' . $section['id'])) ?>" target="_blank"><i class="bi bi-eye"></i> Preview</a>
    </div></div>
    <div class="card a-card"><div class="card-header">Design</div><div class="card-body">
        <?php foreach (section_common_fields() as $key => $f) echo section_field($key, $f, $settings[$key] ?? ''); ?>
    </div></div>
</div></div>
</form>
<?php admin_footer(['editor' => $hasHtml]);
