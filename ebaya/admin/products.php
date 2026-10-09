<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('products.manage');

if (is_post()) {
    csrf_check();
    $act = post('action');
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    if (post('id')) $ids = [(int)post('id')];
    try {
        if ($act === 'duplicate' && $ids) {
            $newId = product_duplicate($ids[0]);
            audit('product_duplicate', 'product', $newId, ['from' => $ids[0]]);
            flash('success', 'Product duplicated as a draft.');
            redirect(admin_url('product-edit?id=' . $newId));
        } elseif ($act === 'delete' && $ids) {
            foreach ($ids as $id) {
                $paths = db_col('SELECT path FROM product_images WHERE product_id = ?', [$id]);
                $p = db_one('SELECT name, slug FROM products WHERE id = ?', [$id]);
                db_exec('DELETE FROM products WHERE id = ?', [$id]);
                foreach ($paths as $path) {
                    if (!db_val('SELECT id FROM product_images WHERE path = ?', [$path])) upload_delete($path);
                }
                audit('product_delete', 'product', $id, $p);
            }
            flash('success', count($ids) . ' product(s) deleted. Past orders keep their item snapshot.');
        } elseif (in_array($act, ['published', 'draft', 'inactive'], true) && $ids) {
            db_exec('UPDATE products SET status = ?, published_at = IF(? = \'published\' AND published_at IS NULL, NOW(), published_at) WHERE id IN (' . db_in($ids) . ')', array_merge([$act, $act], $ids));
            audit('product_bulk_status', 'product', null, ['ids' => $ids, 'status' => $act]);
            flash('success', 'Status updated.');
        }
    } catch (Throwable $e) {
        flash('danger', 'Action failed: ' . $e->getMessage());
    }
    redirect(admin_url('products' . query_with([])));
}

function product_duplicate(int $id): int
{
    return db_tx(function () use ($id) {
        $p = db_one('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) throw new RuntimeException('Not found');
        unset($p['id'], $p['created_at'], $p['updated_at'], $p['published_at']);
        $p['name'] .= ' (Copy)';
        $p['slug'] = unique_slug('products', $p['slug'] . '-copy');
        $sku = $p['sku'] . '-COPY';
        $n = 2;
        while (db_val('SELECT id FROM products WHERE sku = ?', [$sku])) $sku = $p['sku'] . '-COPY' . $n++;
        $p['sku'] = $sku;
        $p['status'] = 'draft';
        $p['rating_avg'] = 0;
        $p['rating_count'] = 0;
        $cols = array_keys($p);
        $newId = db_insert('INSERT INTO products (' . implode(',', $cols) . ') VALUES (' . db_in($cols) . ')', array_values($p));
        db_exec('INSERT INTO product_categories (product_id, category_id) SELECT ?, category_id FROM product_categories WHERE product_id = ?', [$newId, $id]);
        db_exec('INSERT INTO product_attribute_values (product_id, attribute_value_id) SELECT ?, attribute_value_id FROM product_attribute_values WHERE product_id = ?', [$newId, $id]);
        db_exec('INSERT INTO product_images (product_id, color_value_id, path, alt_text, caption, view_type, is_main, sort_order) SELECT ?, color_value_id, path, alt_text, caption, view_type, is_main, sort_order FROM product_images WHERE product_id = ?', [$newId, $id]);
        db_exec('INSERT INTO product_relations (product_id, related_id, relation_type, sort_order) SELECT ?, related_id, relation_type, sort_order FROM product_relations WHERE product_id = ?', [$newId, $id]);
        foreach (db_all('SELECT * FROM product_variants WHERE product_id = ?', [$id]) as $v) {
            $vid = db_insert('INSERT INTO product_variants (product_id, sku, price_override, sale_price_override, weight_grams, is_default, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$newId, $v['sku'] . '-C' . $newId, $v['price_override'], $v['sale_price_override'], $v['weight_grams'], $v['is_default'], $v['status'], $v['sort_order']]);
            db_exec('INSERT INTO product_variant_values (variant_id, attribute_value_id) SELECT ?, attribute_value_id FROM product_variant_values WHERE variant_id = ?', [$vid, (int)$v['id']]);
            db_exec('INSERT INTO product_inventory (variant_id, quantity) VALUES (?, 0)', [$vid]);
        }
        return $newId;
    });
}

$q = mb_substr(get('q'), 0, 100);
$status = in_list(get('status'), ['published', 'draft', 'inactive'], '');
$cat = (int)get('cat');
$where = ['1=1'];
$params = [];
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($cat) { $ids = category_descendant_ids($cat, false); $where[] = '(p.category_id IN (' . db_in($ids) . ') OR p.subcategory_id IN (' . db_in($ids) . '))'; $params = array_merge($params, $ids, $ids); }
$w = implode(' AND ', $where);
$pg = paginate((int)db_val("SELECT COUNT(*) FROM products p WHERE $w", $params), 25, (int)get('page', 1));
$rows = db_all("SELECT p.*, c.name cat_name,
    (SELECT path FROM product_images i WHERE i.product_id = p.id ORDER BY is_main DESC, sort_order LIMIT 1) img,
    (SELECT COALESCE(SUM(inv.quantity),0) FROM product_variants v JOIN product_inventory inv ON inv.variant_id = v.id WHERE v.product_id = p.id AND v.status='active') stock,
    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) variants
    FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $w ORDER BY p.id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);

$admin_title = 'Products';
require __DIR__ . '/partials/header.php';
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <form class="d-flex flex-wrap gap-2" method="get">
    <input class="form-control form-control-sm" style="width:220px" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU">
    <select class="form-select form-select-sm" name="status" style="width:150px"><option value="">All statuses</option><?php foreach (['published', 'draft', 'inactive'] as $s): ?><option value="<?= $s ?>"<?= $status === $s ? ' selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select>
    <select class="form-select form-select-sm" name="cat" style="width:200px"><option value="">All categories</option><?php foreach (categories_options() as $id => $n): ?><option value="<?= $id ?>"<?= $cat === $id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <button class="btn btn-sm btn-outline-secondary">Filter</button>
  </form>
  <a href="<?= e(admin_url('product-edit')) ?>" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Add product</a>
</div>
<form method="post" class="card">
  <?= csrf_field() ?>
  <div class="card-header d-flex gap-2 align-items-center">
    <span class="small text-muted"><?= $pg['total'] ?> products</span>
    <select name="action" class="form-select form-select-sm ms-auto" style="width:180px"><option value="">Bulk action…</option><option value="published">Publish</option><option value="draft">Move to draft</option><option value="inactive">Deactivate</option><option value="delete">Delete</option><option value="duplicate" hidden>Duplicate</option></select>
    <button class="btn btn-sm btn-outline-secondary" onclick="return this.form.action.value!=='delete' || confirm('Delete the selected products?')">Apply</button>
  </div>
  <div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th><input type="checkbox" data-check-all=".row-check"></th><th></th><th>Product</th><th>Category</th><th class="text-end">Price</th><th class="text-end">Stock</th><th>Status</th><th>Flags</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><input type="checkbox" class="row-check" name="ids[]" value="<?= (int)$p['id'] ?>"></td>
        <td><img class="thumb" src="<?= e(img_url($p['img'])) ?>" alt=""></td>
        <td><a href="<?= e(admin_url('product-edit?id=' . $p['id'])) ?>" class="fw-semibold"><?= e($p['name']) ?></a><div class="small text-muted"><?= e($p['sku']) ?> · <?= (int)$p['variants'] ?> variants</div></td>
        <td class="small"><?= e($p['cat_name'] ?? '—') ?></td>
        <td class="text-end"><?php if (product_on_sale($p)): ?><span class="text-danger"><?= money($p['sale_price']) ?></span><br><del class="small text-muted"><?= money($p['regular_price']) ?></del><?php else: ?><?= money($p['regular_price']) ?><?php endif; ?></td>
        <td class="text-end"><?= $p['track_inventory'] ? '<span class="' . ($p['stock'] <= $p['low_stock_threshold'] ? 'text-danger fw-semibold' : '') . '">' . (int)$p['stock'] . '</span>' : '<span class="small text-muted">Made to order</span>' ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td class="small"><?= implode(' ', array_filter([$p['is_featured'] ? '★' : '', $p['is_new_arrival'] ? 'New' : '', $p['is_best_seller'] ? 'Best' : '', $p['is_limited'] ? 'Ltd' : '', $p['is_handcrafted'] ? 'Hand' : ''])) ?></td>
        <td class="text-end text-nowrap">
          <a class="btn btn-sm btn-light" href="<?= e(url('product/' . $p['slug'])) ?>" target="_blank" title="View"><i class="bi bi-eye"></i></a>
          <a class="btn btn-sm btn-light" href="<?= e(admin_url('product-edit?id=' . $p['id'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
          <button class="btn btn-sm btn-light" name="id" value="<?= (int)$p['id'] ?>" onclick="this.form.action.value='duplicate'" title="Duplicate"><i class="bi bi-copy"></i></button>
        </td>
      </tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</form>
<div class="mt-3"><?= admin_pager($pg) ?></div>
<?php require __DIR__ . '/partials/footer.php';
