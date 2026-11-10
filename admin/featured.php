<?php
require __DIR__ . '/includes/init.php';
admin_require('products');
if (is_post()) {
    csrf_check();
    $pid = input_int('product_id');
    $flag = input('flag') === 'is_bestseller' ? 'is_bestseller' : 'is_featured';
    db_exec("UPDATE products SET $flag = ? WHERE id = ?", [input('act') === 'add' ? 1 : 0, $pid]);
    if (is_ajax()) json_out(['ok' => true]);
    flash('success', 'Updated.');
    redirect('admin/featured.php?flag=' . $flag);
}
$flag = input('flag') === 'is_bestseller' ? 'is_bestseller' : 'is_featured';
$rows = db_all("SELECT p.id, p.name, p.sku, p.price, p.sale_price, p.sales_count, (SELECT thumb FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC LIMIT 1) AS thumb FROM products p WHERE p.$flag = 1 AND p.deleted_at IS NULL ORDER BY p.name");
admin_header($flag === 'is_featured' ? 'Featured Products' : 'Best Sellers', 'featured');
?>
<ul class="nav nav-pills mb-3"><li class="nav-item"><a class="nav-link <?= $flag === 'is_featured' ? 'active' : '' ?>" href="?flag=is_featured">Featured</a></li><li class="nav-item"><a class="nav-link <?= $flag === 'is_bestseller' ? 'active' : '' ?>" href="?flag=is_bestseller">Best sellers (manual)</a></li></ul>
<div class="card mb-3"><div class="card-body">
  <label class="form-label">Add a product</label>
  <input type="search" class="form-control js-product-picker" data-results="#fRes" placeholder="Search by name, SKU or barcode…"><div class="list-group mt-1" id="fRes"></div>
  <p class="small text-muted mt-2 mb-0">Homepage sections using the "<?= $flag === 'is_featured' ? 'Featured' : 'Best sellers' ?>" source show these products. Best sellers also rank automatically by sales.</p>
</div></div>
<div class="card"><table class="table align-middle mb-0"><tbody>
<?php foreach ($rows as $p): ?><tr><td style="width:60px"><img src="<?= e(upload_url($p['thumb'])) ?>" class="a-thumb" alt=""></td><td><a href="<?= aurl('product-edit.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a><br><small class="text-muted"><?= e($p['sku']) ?> · sold <?= (int) $p['sales_count'] ?></small></td><td><?= money(effective_price($p['price'], $p['sale_price'])) ?></td>
  <td class="text-end"><form method="post"><?= csrf_field() ?><input type="hidden" name="flag" value="<?= $flag ?>"><input type="hidden" name="act" value="remove"><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-outline-danger">Remove</button></form></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td class="text-muted p-4">None yet.</td></tr><?php endif; ?>
</tbody></table></div>
<?php admin_footer(); ?>
<script>$(document).on('click', '#fRes .js-pick', function () { FSA.post('featured.php', { act: 'add', flag: '<?= $flag ?>', product_id: $(this).data('id') }).done(function () { location.reload(); }); });</script>
