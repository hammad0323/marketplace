<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('content.homepage');
$section = db_one('SELECT * FROM homepage_sections WHERE id = ?', [input_int('id', 0, 'get')]);
if (!$section) {
    redirect(admin_url('homepage'));
}
$schema = section_schemas()[$section['section_type']] ?? null;
if (!$schema) {
    exit('Unknown section type');
}
$fields = $schema['fields'] + (($schema['common'] ?? true) ? section_common_fields() : []);
$current = array_merge(section_defaults($section['section_type']), json_decode((string) ($section['draft_settings'] ?: $section['settings']), true) ?: []);
$errors = [];

if (is_post()) {
    require_csrf();
    $new = [];
    foreach ($fields as $key => $f) {
        switch ($f['type']) {
            case 'bool':
                $new[$key] = input_bool($key) ? '1' : '0';
                break;
            case 'number':
                $new[$key] = input($key) === '' ? '' : (string) (float) input($key);
                break;
            case 'color':
                $new[$key] = valid_hex(input($key)) ? strtoupper(input($key)) : '';
                break;
            case 'select':
                $new[$key] = array_key_exists(input($key), $f['options']) ? input($key) : $f['default'];
                break;
            case 'url':
                $new[$key] = mb_substr(input($key), 0, 255);
                if ($new[$key] !== '' && safe_link($new[$key], '') === '') {
                    $errors[] = $f['label'] . ': use a site path like /shop or a full https:// URL.';
                }
                break;
            case 'image':
                [$path, $err] = handle_image_field($key, $current[$key] ?: null, 'homepage');
                if ($err) {
                    $errors[] = $f['label'] . ': ' . $err;
                }
                $new[$key] = (string) $path;
                break;
            case 'products':
            case 'categories':
                $new[$key] = array_values(array_unique(array_filter(array_map('intval', input_array($key)))));
                break;
            case 'category':
            case 'collection':
                $new[$key] = input_int($key) ?: '';
                break;
            case 'repeater':
                $rows = [];
                foreach (input_array($key) as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $clean = [];
                    foreach ($f['subfields'] as $sk => $sf) {
                        $clean[$sk] = mb_substr(trim((string) ($row[$sk] ?? '')), 0, $sf['type'] === 'textarea' ? 500 : 120);
                    }
                    if (implode('', $clean) !== '') {
                        $rows[] = $clean;
                    }
                }
                $new[$key] = array_slice($rows, 0, $f['max'] ?? 10);
                break;
            case 'textarea':
                $new[$key] = mb_substr(input($key), 0, 5000);
                break;
            default:
                $new[$key] = mb_substr(input($key), 0, 255);
        }
    }
    if (!$errors) {
        $json = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (input('mode') === 'publish') {
            db_exec('UPDATE homepage_sections SET settings = ?, draft_settings = NULL WHERE id = ?', [$json, $section['id']]);
            flash('success', 'Section saved and published.');
        } else {
            db_exec('UPDATE homepage_sections SET draft_settings = ? WHERE id = ?', [$json, $section['id']]);
            flash('success', 'Saved as draft. Preview it, then publish from the homepage builder.');
        }
        audit_log('homepage_section_saved', 'homepage_section', (int) $section['id'], ['mode' => input('mode')]);
        redirect(admin_url('section-edit', ['id' => $section['id']]));
    }
    $current = array_merge($current, $new);
}

function render_field(string $key, array $f, $value): string
{
    $help = $f['help'] ?? null;
    switch ($f['type']) {
        case 'textarea':
            return f_textarea($key, $f['label'], $value, ['rows' => 4], $help);
        case 'number':
            return f_number($key, $f['label'], $value, [], $help);
        case 'bool':
            return f_check($key, $f['label'], $value === '1' || $value === 1 || $value === true, $help);
        case 'color':
            return f_color($key, $f['label'], $value, $help);
        case 'select':
            return f_select($key, $f['label'], $f['options'], $value, [], $help);
        case 'image':
            return f_image($key, $f['label'], $value ?: null, $help);
        case 'products':
            return f_select($key, $f['label'], product_options(), (array) $value, ['multiple' => true, 'size' => 8], 'Ctrl/Cmd-click to choose several. Used when "Hand-picked" is selected.');
        case 'categories':
            return f_select($key, $f['label'], category_options(false), (array) $value, ['multiple' => true, 'size' => 8], 'Used when "Selected below" is chosen. Subcategories may be included.');
        case 'category':
            return f_select($key, $f['label'], category_options(true), $value, [], $help);
        case 'collection':
            return f_select($key, $f['label'], ['' => '— Select —'] + array_column(db_all('SELECT id, name FROM collections ORDER BY name'), 'name', 'id'), $value, [], $help);
        case 'repeater':
            $id = 'rep_' . $key;
            $html = '<label class="form-label">' . e($f['label']) . '</label><div id="' . $id . '" data-max="' . (int) ($f['max'] ?? 10) . '"><div class="repeater-rows">';
            $renderRow = function ($i, $row) use ($f, $key) {
                $h = '<div class="repeater-row"><div class="row g-2">';
                foreach ($f['subfields'] as $sk => $sf) {
                    $name = $key . '[' . $i . '][' . $sk . ']';
                    $v = e($row[$sk] ?? '');
                    $h .= '<div class="' . ($sf['type'] === 'textarea' ? 'col-12' : 'col-md-6') . '"><label class="form-label small">' . e($sf['label']) . '</label>'
                        . ($sf['type'] === 'textarea' ? '<textarea class="form-control form-control-sm" rows="2" name="' . e($name) . '">' . $v . '</textarea>' : '<input class="form-control form-control-sm" name="' . e($name) . '" value="' . $v . '">')
                        . (!empty($sf['help']) ? '<div class="form-text">' . e($sf['help']) . '</div>' : '') . '</div>';
                }
                return $h . '</div><button type="button" class="btn btn-sm btn-link text-danger px-0" data-repeater-remove>Remove</button></div>';
            };
            foreach ((array) $value as $i => $row) {
                $html .= $renderRow($i, $row);
            }
            $html .= '</div><template>' . $renderRow('__i__', []) . '</template></div><button type="button" class="btn btn-sm btn-light mb-3" data-repeater-add="#' . $id . '"><i class="bi bi-plus"></i> Add item</button>';
            return $html;
        default:
            return f_text($key, $f['label'], is_array($value) ? '' : $value, [], $help);
    }
}

admin_header('Edit section: ' . $section['label'], 'homepage');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<?php if ($section['draft_settings']): ?><div class="alert alert-warning py-2">You are editing an unpublished draft of this section.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-header">Content</div><div class="card-body">
    <?php foreach ($schema['fields'] as $k => $f): echo render_field($k, $f, $current[$k] ?? ''); endforeach; ?>
    <?php if ($section['section_type'] === 'hero'): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(admin_url('slides')) ?>"><i class="bi bi-images"></i> Manage slides</a><?php endif; ?>
  </div></div></div>
  <?php if ($schema['common'] ?? true): ?>
  <div class="col-lg-4"><div class="card"><div class="card-header">Layout & style</div><div class="card-body">
    <?php foreach (section_common_fields() as $k => $f): echo render_field($k, $f, $current[$k] ?? ''); endforeach; ?>
  </div></div></div>
  <?php endif; ?>
</div>
<div class="sticky-actions d-flex gap-2">
  <button class="btn btn-outline-primary" name="mode" value="draft">Save draft</button>
  <a class="btn btn-light" target="_blank" href="<?= e(path_url('/', ['preview' => 1])) ?>"><i class="bi bi-eye"></i> Preview</a>
  <button class="btn btn-primary" name="mode" value="publish">Save & publish this section</button>
  <a class="btn btn-light ms-auto" href="<?= e(admin_url('homepage')) ?>">Back to builder</a>
</div>
</form>
<?php admin_footer();
