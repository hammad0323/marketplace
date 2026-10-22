<?php
require __DIR__ . '/../includes/init.php';
admin_require('products');

if (is_post()) {
    $action = input('action', '', 'post');
    $ids = a_ids('ids');
    if ($action === 'duplicate' && ($id = input_int('id'))) {
        $newId = product_duplicate($id);
        flash('success', 'Product duplicated as a draft.');
        redirect('admin/products/edit.php?id=' . $newId);
    }
    if ($action === 'delete' && ($id = input_int('id'))) {
        $ids = [$id];
    }
    if ($ids && in_array($action, ['delete', 'publish', 'draft', 'inactive'], true)) {
        foreach ($ids as $pid) {
            if ($action === 'delete') {
                $p = db_row('SELECT * FROM products WHERE id = ?', [$pid]);
                if (!$p) continue;
                foreach (db_col('SELECT image FROM product_images WHERE product_id = ?', [$pid]) as $img) {
                    delete_upload($img);
                }
                db_exec('DELETE FROM products WHERE id = ?', [$pid]);
                audit_log('product_deleted', 'product', $pid, ['name' => $p['name'], 'sku' => $p['sku']]);
            } else {
                $status = $action === 'publish' ? 'published' : $action;
                db_exec("UPDATE products SET status = ?, published_at = IF(? = 'published' AND published_at IS NULL, NOW(), published_at) WHERE id = ?", [$status, $status, $pid]);
                audit_log('product_status', 'product', $pid, ['status' => $status]);
            }
        }
        flash('success', count($ids) . ' product(s) updated.');
    }
    redirect_back('admin/products/');
}

/** Copies a product with images, variants (new SKUs, zero stock), attributes, relations and collections. */
function product_duplicate(int $id): int
{
    return db_transaction(function () use ($id) {
        $p = db_row('SELECT * FROM products WHERE id = ?', [$id]);
        unset($p['id'], $p['created_at'], $p['updated_at'], $p['published_at']);
        $p['name'] .= ' (Copy)';
        $p['slug'] = unique_slug('products', $p['slug'] . '-copy');
        $base = $p['sku'] . '-COPY';
        $sku = $base;
        $i = 2;
        while (db_val('SELECT 1 FROM products WHERE sku = ?', [$sku])) {
            $sku = $base . $i++;
        }
        $p['sku'] = $sku;
        $p['status'] = 'draft';
        $p['stock_qty'] = 0;
        $p['rating_avg'] = 0;
        $p['rating_count'] = 0;
        $p['view_count'] = 0;
        $newId = db_insert('products', $p);
        foreach (db_all('SELECT * FROM product_images WHERE product_id = ?', [$id]) as $im) {
            unset($im['id'], $im['created_at']);
            $im['product_id'] = $newId;
            db_insert('product_images', $im);
        }
        foreach (product_variants($id, false) as $v) {
            $vid = db_insert('product_variants', ['product_id' => $newId, 'sku' => $sku . '-' . $v['id'], 'price' => $v['price'], 'sale_price' => $v['sale_price'], 'stock_qty' => 0, 'is_active' => $v['is_active'], 'sort_order' => $v['sort_order']]);
            $o = 0;
            foreach ($v['options'] as $name => $val) {
                db_insert('product_variant_values', ['variant_id' => $vid, 'option_name' => $name, 'option_value' => $val, 'sort_order' => $o++]);
            }
        }
        db_exec('INSERT INTO product_attributes (product_id, name, value, sort_order) SELECT ?, name, value, sort_order FROM product_attributes WHERE product_id = ?', [$newId, $id]);
        db_exec('INSERT INTO product_relations (product_id, related_id, relation_type, sort_order) SELECT ?, related_id, relation_type, sort_order FROM product_relations WHERE product_id = ?', [$newId, $id]);
        db_exec('INSERT INTO collection_products (collection_id, product_id, sort_order) SELECT collection_id, ?, sort_order FROM collection_products WHERE product_id = ?', [$newId, $id]);
        audit_log('product_duplicated', 'product', $newId, ['from' => $id]);
        return $newId;
    });
}

$q = input('q', '', 'get');
$cat = input_int('category');
$status = input('status', '', 'get');
$stock = input('stock', '', 'get');
$where = ['1'];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.sku LIKE ?))';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($cat) {
    $ids = category_descendant_ids($cat, false);
    $where[] = 'p.category_id IN (' . db_in($ids) . ')';
    $params = array_merge($params, $ids);
}
if (in_array($status, ['published', 'draft', 'inactive'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
$stockSql = '(CASE WHEN p.has_variants = 1 THEN (SELECT COALESCE(SUM(v.stock_qty),0) FROM product_variants v WHERE v.product_id = p.id AND v.is_active = 1) ELSE p.stock_qty END)';
if ($stock === 'out') {
    $where[] = "p.track_stock = 1 AND $stockSql <= 0";
} elseif ($stock === 'low') {
    $where[] = "p.track_stock = 1 AND $stockSql <= p.low_stock_threshold";
}
$w = implode(' AND ', $where);
$total = (int) db_val("SELECT COUNT(*) FROM products p WHERE $w", $params);
$pg = paginate($total, 30);
$sortMap = ['name' => 'p.name', 'price' => 'p.price', 'stock' => 'stock', 'newest' => 'p.id DESC'];
$sort = $sortMap[input('sort', 'newest', 'get')] ?? 'p.id DESC';
$rows = db_all("SELECT p.*, c.name AS category_name, $stockSql AS stock,
    (SELECT image FROM product_images i WHERE i.product_id = p.id ORDER BY is_main DESC, sort_order LIMIT 1) AS image,
    (SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status NOT IN ('cancelled','refunded')) AS sold
    FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $w ORDER BY $sort LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);

admin_header('Products');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <form class="a-filters mb-0" method="get">
        <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU">
        <select class="form-select" name="category"><option value="">All categories</option>
            <?php foreach (category_options(false) as $o): ?><option value="<?= $o['id'] ?>"<?= $cat === $o['id'] ? ' selected' : '' ?>><?= e($o['label']) ?></option><?php endforeach; ?></select>
        <select class="form-select" name="status"><option value="">Any status</option>
            <?php foreach (['published' => 'Published', 'draft' => 'Draft', 'inactive' => 'Inactive'] as $k => $l): ?><option value="<?= $k ?>"<?= $status === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <select class="form-select" name="stock"><option value="">Any stock</option><option value="low"<?= $stock === 'low' ? ' selected' : '' ?>>Low stock</option><option value="out"<?= $stock === 'out' ? ' selected' : '' ?>>Out of stock</option></select>
        <button class="btn btn-dark">Filter</button>
    </form>
    <a class="btn btn-dark" href="<?= e(url('admin/products/edit.php')) ?>"><i class="bi bi-plus-lg"></i> Add product</a>
</div>
<form method="post" id="bulkForm"><?= csrf_field() ?>
<div class="card a-card">
    <div class="card-header d-flex gap-2 align-items-center"><span class="me-auto"><?= number_format($total) ?> products</span>
        <select class="form-select form-select-sm w-auto" name="action"><option value="">Bulk action…</option><option value="publish">Publish</option><option value="draft">Move to draft</option><option value="inactive">Deactivate</option><option value="delete">Delete</option></select>
        <button class="btn btn-sm btn-outline-dark" onclick="return this.form.action.value !== 'delete' || confirm('Delete the selected products permanently?')">Apply</button></div>
    <div class="table-responsive"><table class="table a-table mb-0">
        <thead><tr><th><input type="checkbox" data-check-all=".pchk"></th><th></th><th><a href="?<?= e(http_build_query(array_merge($_GET, ['sort' => 'name']))) ?>">Product</a></th><th>Category</th><th><a href="?<?= e(http_build_query(array_merge($_GET, ['sort' => 'price']))) ?>">Price</a></th><th><a href="?<?= e(http_build_query(array_merge($_GET, ['sort' => 'stock']))) ?>">Stock</a></th><th>Sold</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): $pi = price_info($p); ?>
            <tr>
                <td><input type="checkbox" class="pchk" name="ids[]" value="<?= (int) $p['id'] ?>"></td>
                <td><div class="a-thumb a-thumb-sm"><?php if ($p['image']): ?><img src="<?= e(img_sized($p['image'], 120)) ?>" alt="" loading="lazy"><?php else: ?><i class="bi bi-image"></i><?php endif; ?></div></td>
                <td><a href="<?= e(url('admin/products/edit.php?id=' . $p['id'])) ?>"><strong><?= e($p['name']) ?></strong></a><div class="small text-muted"><?= e($p['sku']) ?><?= (int) $p['has_variants'] ? ' · variants' : '' ?><?= $p['badge'] ? ' · ' . e($p['badge']) : '' ?></div></td>
                <td class="small"><?= e($p['category_name'] ?? '—') ?></td>
                <td class="text-nowrap"><?php if ($pi['on_sale']): ?><span class="text-danger"><?= e(money($pi['sale'])) ?></span> <s class="small text-muted"><?= e(money($pi['regular'])) ?></s><?php else: ?><?= e(money($pi['final'])) ?><?php endif; ?></td>
                <td><?php if (!(int) $p['track_stock']): ?><span class="text-muted small">Not tracked</span><?php else: ?><span class="fw-semibold <?= $p['stock'] <= 0 ? 'text-danger' : ($p['stock'] <= $p['low_stock_threshold'] ? 'text-warning' : '') ?>"><?= (int) $p['stock'] ?></span><?php endif; ?></td>
                <td><?= (int) $p['sold'] ?></td>
                <td><?= a_badge(ucfirst($p['status']), ['published' => 'success', 'draft' => 'secondary', 'inactive' => 'warning'][$p['status']]) ?></td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-light" href="<?= e(url('product/' . $p['slug'])) ?>" target="_blank" title="View"><i class="bi bi-eye"></i></a>
                    <a class="btn btn-sm btn-light" href="<?= e(url('admin/products/edit.php?id=' . $p['id'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    <button class="btn btn-sm btn-light" title="Duplicate" form="dup<?= (int) $p['id'] ?>"><i class="bi bi-copy"></i></button>
                    <button class="btn btn-sm btn-light text-danger" title="Delete" form="del<?= (int) $p['id'] ?>"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="9"><div class="a-empty"><i class="bi bi-tag"></i><p>No products found.</p></div></td></tr><?php endif; ?>
        </tbody></table></div>
</div>
</form>
<?php foreach ($rows as $p): ?>
    <form method="post" id="dup<?= (int) $p['id'] ?>" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"></form>
    <form method="post" id="del<?= (int) $p['id'] ?>" class="d-none" data-confirm="Delete “<?= e($p['name']) ?>” permanently? Past orders keep their item snapshots."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"></form>
<?php endforeach; ?>
<?= pagination_links($pg, url('admin/products/')) ?>
<?php admin_footer();
