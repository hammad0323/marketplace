<?php
/** Sizes, colours, filter attributes and size guides. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('attributes.manage');

if (is_post()) {
    csrf_check();
    $act = post('action');
    try {
        switch ($act) {
            case 'add_value':
                $aid = (int)post('attribute_id');
                $val = mb_substr(post('value'), 0, 80);
                if ($val === '' || !db_val('SELECT id FROM attributes WHERE id = ?', [$aid])) throw new InvalidArgumentException('Enter a value.');
                $slug = slugify(str_replace('"', 'in', $val));
                if (db_val('SELECT id FROM attribute_values WHERE attribute_id = ? AND slug = ?', [$aid, $slug])) throw new InvalidArgumentException('That value already exists.');
                $hex = v_hex(post('swatch_hex')) ? strtoupper(post('swatch_hex')) : null;
                $sort = (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM attribute_values WHERE attribute_id = ?', [$aid]);
                db_insert('INSERT INTO attribute_values (attribute_id, value, slug, swatch_hex, sort_order) VALUES (?, ?, ?, ?, ?)', [$aid, $val, $slug, $hex, $sort]);
                audit('attribute_value_add', 'attribute', $aid, ['value' => $val]);
                flash('success', 'Value added.');
                break;
            case 'update_value':
                $vid = (int)post('id');
                $val = mb_substr(post('value'), 0, 80);
                if ($val === '') throw new InvalidArgumentException('Value cannot be empty.');
                db_exec('UPDATE attribute_values SET value = ?, swatch_hex = ?, sort_order = ? WHERE id = ?', [$val, v_hex(post('swatch_hex')) ? strtoupper(post('swatch_hex')) : null, (int)post('sort_order'), $vid]);
                flash('success', 'Value updated.');
                break;
            case 'delete_value':
                $vid = (int)post('id');
                if (db_val('SELECT variant_id FROM product_variant_values WHERE attribute_value_id = ? LIMIT 1', [$vid])) throw new InvalidArgumentException('This value is used by product variants — remove it from those variants first.');
                db_exec('DELETE FROM attribute_values WHERE id = ?', [$vid]);
                audit('attribute_value_delete', 'attribute_value', $vid);
                flash('success', 'Value deleted.');
                break;
            case 'add_attribute':
                $name = mb_substr(post('name'), 0, 80);
                $code = preg_replace('/[^a-z0-9_]/', '_', strtolower(post('code') ?: $name));
                if ($name === '' || db_val('SELECT id FROM attributes WHERE code = ?', [$code])) throw new InvalidArgumentException('Enter a unique attribute name.');
                db_insert('INSERT INTO attributes (code, name, is_variant, is_filterable, sort_order) VALUES (?, ?, 0, 1, ?)', [$code, $name, (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM attributes')]);
                flash('success', 'Filter attribute added. Assign values to products in the product editor.');
                break;
            case 'toggle_filter':
                db_exec('UPDATE attributes SET is_filterable = 1 - is_filterable WHERE id = ?', [(int)post('id')]);
                break;
            case 'save_guide':
                $gid = (int)post('id');
                $name = mb_substr(post('name'), 0, 120);
                $content = sanitize_html((string)($_POST['content'] ?? ''));
                if ($name === '') throw new InvalidArgumentException('Guide name is required.');
                if ($gid) db_exec('UPDATE size_guides SET name = ?, content = ? WHERE id = ?', [$name, $content, $gid]);
                else db_insert('INSERT INTO size_guides (name, content) VALUES (?, ?)', [$name, $content]);
                audit('size_guide_save', 'size_guide', $gid ?: null, ['name' => $name]);
                flash('success', 'Size guide saved.');
                break;
            case 'delete_guide':
                db_exec('DELETE FROM size_guides WHERE id = ?', [(int)post('id')]);
                flash('success', 'Size guide deleted.');
                break;
        }
    } catch (InvalidArgumentException $e) {
        flash('danger', $e->getMessage());
    }
    redirect(admin_url('attributes'));
}

$attrs = db_all('SELECT * FROM attributes ORDER BY sort_order');
$vals = [];
foreach (db_all('SELECT av.*, (SELECT COUNT(*) FROM product_variant_values pvv WHERE pvv.attribute_value_id = av.id) + (SELECT COUNT(*) FROM product_attribute_values pav WHERE pav.attribute_value_id = av.id) uses FROM attribute_values av ORDER BY sort_order, id') as $v) $vals[(int)$v['attribute_id']][] = $v;
$guides = db_all('SELECT * FROM size_guides ORDER BY name');
$editGuide = get('guide') !== '' ? (db_one('SELECT * FROM size_guides WHERE id = ?', [(int)get('guide')]) ?: ['id' => 0, 'name' => '', 'content' => '']) : null;

$admin_title = 'Sizes, colours & attributes';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-3">
  <?php foreach ($attrs as $a): ?>
    <div class="col-lg-6"><div class="card h-100">
      <div class="card-header d-flex align-items-center"><?= e($a['name']) ?> <span class="badge text-bg-light ms-2"><?= $a['is_variant'] ? 'Variant option' : 'Filter / detail' ?></span>
        <form method="post" class="ms-auto"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_filter"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-sm btn-light"><?= $a['is_filterable'] ? 'Shown in filters' : 'Hidden from filters' ?></button></form></div>
      <div class="card-body">
        <table class="table table-sm mb-2"><tbody>
          <?php foreach ($vals[(int)$a['id']] ?? [] as $v): ?>
            <tr><td colspan="4"><form method="post" class="d-flex gap-2 align-items-center"><?= csrf_field() ?><input type="hidden" name="action" value="update_value"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
              <input class="form-control form-control-sm" name="value" value="<?= e($v['value']) ?>">
              <?php if ($a['code'] === 'color'): ?><input type="color" class="form-control form-control-sm form-control-color" name="swatch_hex" value="<?= e($v['swatch_hex'] ?: '#cccccc') ?>"><?php endif; ?>
              <input class="form-control form-control-sm" type="number" name="sort_order" value="<?= (int)$v['sort_order'] ?>" style="width:70px" title="Sort">
              <span class="small text-muted text-nowrap"><?= (int)$v['uses'] ?> uses</span>
              <button class="btn btn-sm btn-light" title="Save"><i class="bi bi-check2"></i></button>
            </form></td>
            <td><form method="post" data-confirm="Delete “<?= e($v['value']) ?>”?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_value"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></td></tr>
          <?php endforeach; ?>
        </tbody></table>
        <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="add_value"><input type="hidden" name="attribute_id" value="<?= (int)$a['id'] ?>">
          <input class="form-control form-control-sm" name="value" placeholder="<?= $a['code'] === 'size' ? 'e.g. XXL or 62"' : 'New value' ?>" required>
          <?php if ($a['code'] === 'color'): ?><input type="color" class="form-control form-control-sm form-control-color" name="swatch_hex" value="#cccccc"><?php endif; ?>
          <button class="btn btn-sm btn-primary">Add</button></form>
      </div>
    </div></div>
  <?php endforeach; ?>
  <div class="col-lg-6"><div class="card"><div class="card-header">Add a filter attribute</div><div class="card-body">
    <form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="add_attribute"><input class="form-control form-control-sm" name="name" placeholder="e.g. Fabric" required><button class="btn btn-sm btn-primary">Add</button></form>
    <p class="small text-muted mt-2 mb-0">Size and colour create variants with their own SKU and stock. Other attributes describe products and appear as shop filters.</p>
  </div></div></div>
</div>

<div class="card mt-3"><div class="card-header d-flex">Size guides <a class="btn btn-sm btn-primary ms-auto" href="?guide=0">Add size guide</a></div>
  <ul class="list-group list-group-flush">
    <?php foreach ($guides as $g): ?><li class="list-group-item d-flex align-items-center"><?= e($g['name']) ?><a class="btn btn-sm btn-light ms-auto" href="?guide=<?= (int)$g['id'] ?>">Edit</a>
      <form method="post" data-confirm="Delete this size guide?" class="ms-2"><?= csrf_field() ?><input type="hidden" name="action" value="delete_guide"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></li><?php endforeach; ?>
  </ul>
  <?php if ($editGuide !== null): ?>
  <div class="card-body border-top"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save_guide"><input type="hidden" name="id" value="<?= (int)$editGuide['id'] ?>">
    <?= f_text('name', 'Name', $editGuide['name'], ['required' => true]) ?>
    <?= f_text('content', 'Content (HTML table allowed)', $editGuide['content'], ['type' => 'textarea', 'rows' => 12]) ?>
    <button class="btn btn-primary">Save size guide</button></form></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php';
