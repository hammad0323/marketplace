<?php
require __DIR__ . '/_inc/bootstrap.php';
admin_post('products.edit');
$action = input('action');
$ids = array_values(array_filter(array_map('intval', input_array('ids') ?: [input_int('id')])));
if (!$ids) {
    flash('warning', 'Select at least one product.');
    admin_back('products');
}

/** Duplicate a product (as draft) with images, variants, relations and collections. */
function duplicate_product(int $id): int
{
    return db_tx(function () use ($id) {
        $p = db_one('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) {
            throw new RuntimeException('Product not found');
        }
        unset($p['id'], $p['created_at'], $p['updated_at'], $p['published_at']);
        $p['name'] .= ' (Copy)';
        $p['slug'] = unique_slug('products', $p['slug'] . '-copy');
        $sku = $p['sku'] . '-COPY';
        $n = 2;
        while (db_val('SELECT COUNT(*) FROM products WHERE sku = ?', [$sku])) {
            $sku = $p['sku'] . '-COPY' . $n++;
        }
        $p['sku'] = $sku;
        $p['status'] = 'draft';
        $p['view_count'] = 0;
        $newId = db_insert('products', $p);
        $imgMap = [];
        foreach (db_all('SELECT * FROM product_images WHERE product_id = ?', [$id]) as $img) {
            $old = $img['id'];
            unset($img['id'], $img['created_at']);
            $img['product_id'] = $newId;
            $imgMap[$old] = db_insert('product_images', $img);
        }
        foreach (db_all('SELECT * FROM product_variants WHERE product_id = ?', [$id]) as $v) {
            $oldV = $v['id'];
            unset($v['id'], $v['created_at'], $v['updated_at']);
            $v['product_id'] = $newId;
            $v['sku'] = $v['sku'] . '-' . $newId;
            $v['image_id'] = $v['image_id'] ? ($imgMap[$v['image_id']] ?? null) : null;
            $newV = db_insert('product_variants', $v);
            db_exec('INSERT INTO product_variant_values (variant_id, attribute_name, attribute_value) SELECT ?, attribute_name, attribute_value FROM product_variant_values WHERE variant_id = ?', [$newV, $oldV]);
            db_insert('product_inventory', ['product_id' => $newId, 'variant_id' => $newV, 'quantity' => 0]);
        }
        if (!db_val('SELECT COUNT(*) FROM product_variants WHERE product_id = ?', [$newId])) {
            db_insert('product_inventory', ['product_id' => $newId, 'variant_id' => null, 'quantity' => 0]);
        }
        db_exec('INSERT INTO product_relations (product_id, related_id, relation_type, sort_order) SELECT ?, related_id, relation_type, sort_order FROM product_relations WHERE product_id = ?', [$newId, $id]);
        db_exec('INSERT INTO collection_products (collection_id, product_id, sort_order) SELECT collection_id, ?, sort_order FROM collection_products WHERE product_id = ?', [$newId, $id]);
        audit_log('product_duplicated', 'product', $newId, ['source' => $id]);
        return $newId;
    });
}

$done = 0;
$skipped = [];
foreach ($ids as $id) {
    switch ($action) {
        case 'publish':
        case 'draft':
        case 'inactive':
            $status = $action === 'publish' ? 'published' : $action;
            db_exec('UPDATE products SET status = ?, published_at = IF(? = \'published\' AND published_at IS NULL, NOW(), published_at) WHERE id = ?', [$status, $status, $id]);
            audit_log('product_status', 'product', $id, ['status' => $status]);
            $done++;
            break;
        case 'duplicate':
            $newId = duplicate_product($id);
            $done++;
            if (count($ids) === 1) {
                flash('success', 'Product duplicated as a draft. Stock for the copy starts at zero.');
                redirect(admin_url('product-edit', ['id' => $newId]));
            }
            break;
        case 'delete':
            require_admin('products.delete');
            $p = db_one('SELECT * FROM products WHERE id = ?', [$id]);
            if (!$p) {
                break;
            }
            if (db_val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id])) {
                // Keep sales history intact: archive instead of deleting.
                db_exec("UPDATE products SET status = 'inactive' WHERE id = ?", [$id]);
                $skipped[] = $p['name'];
                break;
            }
            $files = db_col('SELECT file_path FROM product_images WHERE product_id = ?', [$id]);
            db_tx(function () use ($id) {
                db_exec('DELETE FROM products WHERE id = ?', [$id]);
            });
            foreach ($files as $f) {
                if (!db_val('SELECT COUNT(*) FROM product_images WHERE file_path = ?', [$f])) {
                    delete_upload($f);
                }
            }
            if ($p['og_image'] && !db_val('SELECT COUNT(*) FROM products WHERE og_image = ?', [$p['og_image']])) {
                delete_upload($p['og_image']);
            }
            db_exec('INSERT INTO redirects (source_path, target_path, status_code, is_auto) VALUES (?, ?, 301, 1) ON DUPLICATE KEY UPDATE target_path = VALUES(target_path), is_active = 1',
                ['/product/' . $p['slug'], '/category/' . db_val('SELECT slug FROM categories WHERE id = ?', [$p['category_id']])]);
            audit_log('product_deleted', 'product', $id, ['name' => $p['name'], 'sku' => $p['sku']]);
            $done++;
            break;
        default:
            flash('error', 'Unknown action.');
            admin_back('products');
    }
}
if ($skipped) {
    flash('warning', 'These products have orders, so they were set to inactive instead of deleted (order history is preserved): ' . implode(', ', $skipped));
}
flash('success', $done . ' product(s) updated.');
admin_back('products');
