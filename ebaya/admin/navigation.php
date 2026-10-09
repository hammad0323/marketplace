<?php
/** Header & footer menus. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('homepage.manage');
$menus = ['header' => 'Header navigation', 'footer_shop' => 'Footer — Shop', 'footer_help' => 'Footer — Customer care', 'footer_about' => 'Footer — Ebaya'];
if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'reorder') {
        foreach (array_values((array)($_POST['ids'] ?? [])) as $i => $nid) db_exec('UPDATE navigation_items SET sort_order = ? WHERE id = ?', [$i + 1, (int)$nid]);
        json_out(['ok' => true]);
    }
    if ($act === 'delete') {
        db_exec('DELETE FROM navigation_items WHERE id = ?', [(int)post('id')]);
        flash('success', 'Link removed.');
    } elseif ($act === 'save') {
        $nid = (int)post('id');
        $menu = in_list(post('menu'), array_keys($menus), 'header');
        $url = clean_url(post('url'));
        $label = mb_substr(post('label'), 0, 80);
        if ($label === '' || $url === '') { flash('danger', 'Label and a valid link (e.g. /shop or https://…) are required.'); redirect(admin_url('navigation')); }
        $parent = (int)post('parent_id') ?: null;
        if ($parent && ($parent === $nid || $menu !== 'header')) $parent = null;
        $d = [$menu, $parent, $label, $url, (int)post('sort_order'), post('is_visible') ? 1 : 0, post('new_tab') ? 1 : 0, v_hex(post('text_color')) ? post('text_color') : null];
        if ($nid) db_exec('UPDATE navigation_items SET menu=?, parent_id=?, label=?, url=?, sort_order=?, is_visible=?, new_tab=?, text_color=? WHERE id = ?', array_merge($d, [$nid]));
        else db_insert('INSERT INTO navigation_items (menu, parent_id, label, url, sort_order, is_visible, new_tab, text_color) VALUES (?,?,?,?,?,?,?,?)', $d);
        flash('success', 'Link saved.');
    }
    audit('navigation_' . $act, 'navigation', (int)post('id') ?: null);
    redirect(admin_url('navigation'));
}
$items = db_all('SELECT * FROM navigation_items ORDER BY menu, sort_order, id');
$top = array_filter($items, fn($i) => $i['menu'] === 'header' && $i['parent_id'] === null);
$edit = get('edit') !== '' ? (db_one('SELECT * FROM navigation_items WHERE id = ?', [(int)get('edit')]) ?: ['id' => 0, 'menu' => get('menu') ?: 'header']) : null;
$admin_title = 'Navigation';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-3"><div class="col-xl-7">
<?php foreach ($menus as $mk => $ml): ?>
  <div class="card mb-3"><div class="card-header d-flex"><?= e($ml) ?><a class="btn btn-sm btn-light ms-auto" href="?edit=0&menu=<?= $mk ?>">Add link</a></div><div class="card-body" data-sortable="<?= e(admin_url('navigation')) ?>">
    <?php foreach ($items as $n): if ($n['menu'] !== $mk || $n['parent_id'] !== null) continue; ?>
      <div class="section-row" data-id="<?= (int)$n['id'] ?>"><i class="bi bi-grip-vertical drag-handle"></i><div class="flex-grow-1"><strong<?= $n['text_color'] ? ' style="color:' . e($n['text_color']) . '"' : '' ?>><?= e($n['label']) ?></strong> <span class="small text-muted"><?= e($n['url']) ?></span><?= $n['is_visible'] ? '' : ' <span class="badge text-bg-secondary">hidden</span>' ?>
        <?php foreach ($items as $c): if ((int)$c['parent_id'] !== (int)$n['id']) continue; ?><div class="small ms-3">↳ <a href="?edit=<?= (int)$c['id'] ?>"><?= e($c['label']) ?></a> <span class="text-muted"><?= e($c['url']) ?></span><?= $c['is_visible'] ? '' : ' (hidden)' ?></div><?php endforeach; ?></div>
        <a class="btn btn-sm btn-light" href="?edit=<?= (int)$n['id'] ?>"><i class="bi bi-pencil"></i></a>
        <form method="post" data-confirm="Remove this link<?= $mk === 'header' ? ' and its dropdown items' : '' ?>?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$n['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form></div>
    <?php endforeach; ?>
  </div></div>
<?php endforeach; ?>
</div><div class="col-xl-5"><?php if ($edit !== null): $nv = fn($k, $d = '') => $edit[$k] ?? $d; ?>
  <div class="card"><div class="card-header"><?= $nv('id') ? 'Edit link' : 'New link' ?></div><div class="card-body"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$nv('id', 0) ?>">
    <?= f_select('menu', 'Menu', $menus, $nv('menu', 'header')) ?>
    <?= f_text('label', 'Label', $nv('label'), ['required' => true]) ?>
    <?= f_text('url', 'Link', $nv('url'), ['required' => true, 'placeholder' => '/category/crochet-flower-abayas', 'help' => 'Site paths start with /. External links need https://.']) ?>
    <?= f_select('parent_id', 'Dropdown under (header only)', array_column($top, 'label', 'id'), $nv('parent_id'), ['empty' => '— Top level —']) ?>
    <?= f_text('text_color', 'Highlight colour (optional, e.g. for a Sale link)', $nv('text_color'), ['placeholder' => '#B89A64']) ?>
    <?= f_text('sort_order', 'Order', $nv('sort_order', 0), ['type' => 'number']) ?>
    <?= f_toggle('is_visible', 'Visible', $nv('is_visible', 1)) ?><?= f_toggle('new_tab', 'Open in new tab', $nv('new_tab')) ?>
    <button class="btn btn-primary">Save link</button></form></div></div>
<?php else: ?><div class="card"><div class="card-body small text-muted">Header links with children become animated dropdowns on desktop and expandable menus on mobile. Layout and mobile style are in Theme.</div></div><?php endif; ?></div></div>
<?php require __DIR__ . '/partials/footer.php';
