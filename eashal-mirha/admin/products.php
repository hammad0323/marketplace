<?php
require __DIR__ . '/includes/admin.php';

if (is_post()) {
    require_csrf();
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    if (post('id')) $ids = [(int)post('id')];
    $act = post('bulk') ?: post('do');
    if ($ids && $act) {
        $in = implode(',', $ids);
        switch ($act) {
            case 'delete':
                foreach (rows("SELECT image FROM product_images WHERE product_id IN ($in)") as $im) {
                    if (!val("SELECT COUNT(*) FROM product_images WHERE image = ? AND product_id NOT IN ($in)", [$im['image']])) delete_upload($im['image']);
                }
                q("DELETE FROM product_images WHERE product_id IN ($in)");
                q("DELETE FROM reviews WHERE product_id IN ($in)");
                q("DELETE FROM wishlist WHERE product_id IN ($in)");
                q("DELETE FROM products WHERE id IN ($in)");
                flash('success', count($ids) . ' product(s) deleted.');
                break;
            case 'activate':   q("UPDATE products SET status = 1 WHERE id IN ($in)"); flash('success', 'Products published.'); break;
            case 'deactivate': q("UPDATE products SET status = 0 WHERE id IN ($in)"); flash('success', 'Products hidden.'); break;
            case 'new_on':     q("UPDATE products SET is_new = 1 WHERE id IN ($in)"); flash('success', 'Marked as New Arrival.'); break;
            case 'new_off':    q("UPDATE products SET is_new = 0 WHERE id IN ($in)"); flash('success', 'Removed from New Arrivals.'); break;
            case 'best_on':    q("UPDATE products SET is_bestseller = 1 WHERE id IN ($in)"); flash('success', 'Marked as Best Seller.'); break;
            case 'best_off':   q("UPDATE products SET is_bestseller = 0 WHERE id IN ($in)"); flash('success', 'Removed from Best Sellers.'); break;
            case 'duplicate':
                foreach ($ids as $id) {
                    $p = row('SELECT * FROM products WHERE id = ?', [$id]);
                    if (!$p) continue;
                    unset($p['id']);
                    $p['name'] .= ' (Copy)';
                    $p['slug'] = unique_slug('products', $p['slug'] . '-copy');
                    $p['sku'] = $p['sku'] ? $p['sku'] . '-C' : null;
                    $p['status'] = 0; $p['views'] = 0; $p['sales_count'] = 0;
                    $p['created_at'] = date('Y-m-d H:i:s'); $p['updated_at'] = null;
                    q('INSERT INTO products (' . implode(',', array_keys($p)) . ') VALUES (' . rtrim(str_repeat('?,', count($p)), ',') . ')', array_values($p));
                    $nid = (int)db()->lastInsertId();
                    foreach (product_images($id) as $im) q('INSERT INTO product_images (product_id, image, sort_order) VALUES (?, ?, ?)', [$nid, $im['image'], $im['sort_order']]);
                }
                flash('success', 'Product duplicated as a hidden draft.');
                break;
        }
    }
    back('admin/products');
}

$where = ['1'];
$params = [];
if (get('q') !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; array_push($params, '%' . get('q') . '%', '%' . get('q') . '%'); }
if (get('cat') !== '') { $where[] = '(p.category_id = ? OR p.subcategory_id = ?)'; array_push($params, (int)get('cat'), (int)get('cat')); }
if (get('status') !== '') { $where[] = 'p.status = ?'; $params[] = (int)get('status'); }
if (get('stock') === 'low') { $where[] = 'p.stock <= ?'; $params[] = (int)setting('low_stock', 3); }
if (get('flag') === 'new') $where[] = 'p.is_new = 1';
if (get('flag') === 'best') $where[] = 'p.is_bestseller = 1';
if (get('flag') === 'featured') $where[] = 'p.is_featured = 1';
$w = implode(' AND ', $where);
$sorts = ['new' => 'p.id DESC', 'name' => 'p.name', 'price' => 'p.price', 'stock' => 'p.stock', 'sales' => 'p.sales_count DESC'];
$sort = $sorts[get('sort')] ?? 'p.id DESC';
$total = (int)val("SELECT COUNT(*) FROM products p WHERE $w", $params);
$pg = paginate($total, 25, (int)get('page', 1));
$products = attach_images(rows("SELECT p.*, c.name cat, s.name sub FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN categories s ON s.id = p.subcategory_id WHERE $w ORDER BY $sort LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params));
$cats = category_tree();

admin_header('Products', 'products');
?>
<div class="toolbar">
  <form class="filters" method="get">
    <input type="search" name="q" value="<?= e(get('q')) ?>" placeholder="Search name or SKU">
    <select name="cat"><option value="">All categories</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= get('cat') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php foreach ($c['children'] as $s): ?><option value="<?= $s['id'] ?>" <?= get('cat') == $s['id'] ? 'selected' : '' ?>>&nbsp;&nbsp;— <?= e($s['name']) ?></option><?php endforeach; ?>
      <?php endforeach; ?>
    </select>
    <select name="status"><option value="">Any status</option><option value="1" <?= get('status') === '1' ? 'selected' : '' ?>>Published</option><option value="0" <?= get('status') === '0' ? 'selected' : '' ?>>Hidden</option></select>
    <select name="flag"><option value="">All</option><option value="new" <?= get('flag') === 'new' ? 'selected' : '' ?>>New Arrivals</option><option value="best" <?= get('flag') === 'best' ? 'selected' : '' ?>>Best Sellers</option><option value="featured" <?= get('flag') === 'featured' ? 'selected' : '' ?>>Featured</option></select>
    <select name="stock"><option value="">Any stock</option><option value="low" <?= get('stock') === 'low' ? 'selected' : '' ?>>Low stock</option></select>
    <select name="sort"><option value="new">Newest</option><?php foreach (['name' => 'Name', 'price' => 'Price', 'stock' => 'Stock', 'sales' => 'Best selling'] as $k => $l): ?><option value="<?= $k ?>" <?= get('sort') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn">Filter</button>
  </form>
  <a class="btn btn-primary" href="<?= url('admin/product-edit') ?>"><?= aicon('plus') ?> Add Product</a>
</div>

<form method="post" class="card" id="bulkForm">
  <?= csrf_field() ?>
  <div class="bulk-bar">
    <select name="bulk"><option value="">Bulk actions</option><option value="activate">Publish</option><option value="deactivate">Hide</option><option value="new_on">Mark New Arrival</option><option value="new_off">Unmark New Arrival</option><option value="best_on">Mark Best Seller</option><option value="best_off">Unmark Best Seller</option><option value="duplicate">Duplicate</option><option value="delete">Delete</option></select>
    <button class="btn btn-sm" data-confirm-bulk>Apply</button>
    <span class="muted"><?= $total ?> products</span>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th class="w-check"><input type="checkbox" data-check-all></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Sold</th><th>Flags</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= $p['id'] ?>"></td>
        <td><div class="prod-cell"><img src="<?= e(img($p['image'])) ?>" alt=""><div><a href="<?= url('admin/product-edit?id=' . $p['id']) ?>"><strong><?= e($p['name']) ?></strong></a><small class="block muted"><?= e($p['sku']) ?></small></div></div></td>
        <td><?= e($p['cat']) ?><small class="block muted"><?= e($p['sub']) ?></small></td>
        <td><?= money(price_now($p)) ?><?php if (on_sale($p)): ?><small class="block muted"><del><?= money($p['price']) ?></del></small><?php endif; ?></td>
        <td class="<?= $p['stock'] <= (int)setting('low_stock', 3) ? 'text-danger' : '' ?>"><?= (int)$p['stock'] ?></td>
        <td><?= (int)$p['sales_count'] ?></td>
        <td class="flags"><?= $p['is_new'] ? '<span class="pill">New</span>' : '' ?><?= $p['is_bestseller'] ? '<span class="pill pill-gold">Best</span>' : '' ?><?= $p['is_featured'] ? '<span class="pill">Featured</span>' : '' ?></td>
        <td><?= $p['status'] ? '<span class="badge badge-delivered">Published</span>' : '<span class="badge">Hidden</span>' ?></td>
        <td class="actions">
          <a class="icon" href="<?= url('admin/product-edit?id=' . $p['id']) ?>" title="Edit"><?= aicon('edit') ?></a>
          <a class="icon" href="<?= url('product/' . $p['slug']) ?>" target="_blank" title="View"><?= aicon('eye') ?></a>
          <button class="icon" form="rowAct" name="id" value="<?= $p['id'] ?>" data-do="duplicate" title="Duplicate"><?= aicon('copy') ?></button>
          <button class="icon danger" form="rowAct" name="id" value="<?= $p['id'] ?>" data-do="delete" data-confirm="Delete “<?= e($p['name']) ?>” permanently?" title="Delete"><?= aicon('trash') ?></button>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="9" class="empty">No products found.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</form>
<form method="post" id="rowAct"><?= csrf_field() ?><input type="hidden" name="do" value=""></form>
<?= page_links($pg) ?>
<?php admin_footer();
