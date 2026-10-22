<?php
/** Master lists of sizes and colours used by the variant builder and filters. */
require __DIR__ . '/../includes/init.php';
admin_require('attributes');
if (is_post()) {
    $a = input('action', '', 'post');
    if ($a === 'add_size') {
        $g = mb_substr(input('size_group', 'Apparel', 'post'), 0, 40) ?: 'Apparel';
        foreach (array_filter(array_map('trim', explode(',', input('names', '', 'post')))) as $n) {
            db_exec('INSERT IGNORE INTO sizes (size_group, name, sort_order) VALUES (?, ?, ?)', [$g, mb_substr($n, 0, 40), (int) db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM sizes')]);
        }
    } elseif ($a === 'add_color') {
        $hex = safe_color(input('hex_code', '', 'post'), '#000000');
        $n = mb_substr(input('name', '', 'post'), 0, 60);
        if ($n !== '') db_exec('INSERT INTO colors (name, hex_code, sort_order) VALUES (?, ?, 99) ON DUPLICATE KEY UPDATE hex_code = VALUES(hex_code)', [$n, $hex]);
    } elseif ($a === 'save') {
        foreach ((array) ($_POST['size_order'] ?? []) as $sid => $o) db_exec('UPDATE sizes SET sort_order = ? WHERE id = ?', [(int) $o, (int) $sid]);
        foreach ((array) ($_POST['color_hex'] ?? []) as $cid => $h) db_exec('UPDATE colors SET hex_code = ? WHERE id = ?', [safe_color((string) $h, '#000000'), (int) $cid]);
    } elseif ($a === 'del_size') {
        db_exec('DELETE FROM sizes WHERE id = ?', [input_int('id')]);
    } elseif ($a === 'del_color') {
        db_exec('DELETE FROM colors WHERE id = ?', [input_int('id')]);
    }
    audit_log('attributes_' . $a, 'attributes');
    flash('success', 'Saved.');
    redirect('admin/catalog/attributes.php');
}
$sizes = db_all('SELECT * FROM sizes ORDER BY size_group, sort_order, id');
$colors = db_all('SELECT * FROM colors ORDER BY sort_order, name');
admin_header('Sizes & colours');
?>
<p class="text-muted">These lists power the variant builder presets, size ordering on product pages and colour swatches. Product-specific values can also be typed directly on any product.</p>
<div class="row g-4">
<div class="col-lg-6"><div class="card a-card"><div class="card-header">Sizes</div><div class="card-body">
    <form method="post" class="row g-2 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="add_size">
        <div class="col-4"><input class="form-control" name="size_group" placeholder="Group (Apparel, Waist, Shoe…)" list="groups"></div>
        <div class="col-6"><input class="form-control" name="names" placeholder="Sizes, comma separated" required></div>
        <div class="col-2"><button class="btn btn-dark w-100">Add</button></div></form>
    <datalist id="groups"><?php foreach (array_unique(array_column($sizes, 'size_group')) as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
    <table class="table a-table"><thead><tr><th>Group</th><th>Size</th><th>Order</th><th></th></tr></thead><tbody>
    <?php foreach ($sizes as $s): ?><tr><td><?= e($s['size_group']) ?></td><td><strong><?= e($s['name']) ?></strong></td><td style="width:90px"><input class="form-control form-control-sm" type="number" name="size_order[<?= (int) $s['id'] ?>]" value="<?= (int) $s['sort_order'] ?>"></td>
    <td class="text-end"><button class="btn btn-sm btn-light text-danger" form="ds<?= (int) $s['id'] ?>"><i class="bi bi-x"></i></button></td></tr><?php endforeach; ?>
    </tbody></table><button class="btn btn-outline-dark btn-sm">Save order</button></form>
</div></div></div>
<div class="col-lg-6"><div class="card a-card"><div class="card-header">Colours</div><div class="card-body">
    <form method="post" class="row g-2 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="add_color">
        <div class="col-6"><input class="form-control" name="name" placeholder="Colour name" required></div>
        <div class="col-4"><input class="form-control form-control-color w-100" type="color" name="hex_code" value="#111111"></div>
        <div class="col-2"><button class="btn btn-dark w-100">Add</button></div></form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save">
    <table class="table a-table"><thead><tr><th>Swatch</th><th>Name</th><th></th></tr></thead><tbody>
    <?php foreach ($colors as $c): ?><tr><td style="width:80px"><input class="form-control form-control-color" type="color" name="color_hex[<?= (int) $c['id'] ?>]" value="<?= e($c['hex_code']) ?>"></td><td><?= e($c['name']) ?></td>
    <td class="text-end"><button class="btn btn-sm btn-light text-danger" form="dc<?= (int) $c['id'] ?>"><i class="bi bi-x"></i></button></td></tr><?php endforeach; ?>
    </tbody></table><button class="btn btn-outline-dark btn-sm">Save colours</button></form>
</div></div></div>
</div>
<?php foreach ($sizes as $s): ?><form id="ds<?= (int) $s['id'] ?>" method="post" class="d-none" data-confirm="Remove size <?= e($s['name']) ?> from the master list? Existing variants are not changed."><?= csrf_field() ?><input type="hidden" name="action" value="del_size"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"></form><?php endforeach; ?>
<?php foreach ($colors as $c): ?><form id="dc<?= (int) $c['id'] ?>" method="post" class="d-none" data-confirm="Remove colour <?= e($c['name']) ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="del_color"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"></form><?php endforeach; ?>
<?php admin_footer();
