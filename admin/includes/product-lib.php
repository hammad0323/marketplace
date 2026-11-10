<?php
/**
 * Shared product save logic (product editor, quick add, CSV import).
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

/** Returns error string or null. Checks SKU/barcode uniqueness across products & variations. */
function product_code_conflict(string $sku, ?string $barcode, int $productId = 0, int $variationId = 0): ?string
{
    if ($sku !== '') {
        if (db_val('SELECT id FROM products WHERE sku = ? AND id <> ?', [$sku, $productId])) return 'SKU "' . $sku . '" is already used by another product.';
        if (db_val('SELECT id FROM product_variations WHERE sku = ? AND id <> ?', [$sku, $variationId])) return 'SKU "' . $sku . '" is already used by a product variation.';
    }
    if ($barcode) {
        if (db_val('SELECT id FROM products WHERE barcode = ? AND id <> ? AND deleted_at IS NULL', [$barcode, $productId])) return 'Barcode "' . $barcode . '" is already used.';
        if (db_val('SELECT id FROM product_variations WHERE barcode = ? AND id <> ?', [$barcode, $variationId])) return 'Barcode "' . $barcode . '" is already used by a variation.';
    }
    return null;
}

function product_next_sku(): string
{
    $n = (int) db_val("SELECT MAX(CAST(SUBSTRING(sku, 4) AS UNSIGNED)) FROM products WHERE sku REGEXP '^FS-[0-9]+$'");
    do { $n++; $sku = 'FS-' . $n; } while (db_val('SELECT id FROM products WHERE sku = ?', [$sku]));
    return $sku;
}

/**
 * Create/update the core product row + categories + tags.
 * $d keys match products columns plus: category_ids[], tags (comma string)
 * @return int|string product id or error
 */
function product_save_core(int $id, array $d)
{
    $name = trim((string) ($d['name'] ?? ''));
    if ($name === '') return 'Product name is required.';
    $sku = trim((string) ($d['sku'] ?? '')) ?: ($id ? (string) db_val('SELECT sku FROM products WHERE id = ?', [$id]) : product_next_sku());
    $barcode = trim((string) ($d['barcode'] ?? '')) ?: null;
    if ($err = product_code_conflict($sku, $barcode, $id)) return $err;
    $price = round(max(0, (float) ($d['price'] ?? 0)), 2);
    $sale = isset($d['sale_price']) && $d['sale_price'] !== '' && $d['sale_price'] !== null ? round(max(0, (float) $d['sale_price']), 2) : null;
    if ($sale !== null && $sale >= $price && $price > 0) return 'Sale price must be lower than the regular price.';
    $cats = array_values(array_unique(array_filter(array_map('intval', $d['category_ids'] ?? []))));
    $primary = (int) ($d['primary_category_id'] ?? 0) ?: ($cats[0] ?? null);
    if ($primary && !in_array($primary, $cats, true)) $cats[] = $primary;
    $old = $id ? db_row('SELECT slug FROM products WHERE id = ?', [$id]) : null;
    $row = [
        'name' => mb_substr($name, 0, 220),
        'name_urdu' => trim((string) ($d['name_urdu'] ?? '')) ?: null,
        'slug' => unique_slug('products', trim((string) ($d['slug'] ?? '')) ?: $name, $id),
        'sku' => mb_substr($sku, 0, 64),
        'barcode' => $barcode,
        'product_code' => trim((string) ($d['product_code'] ?? '')) ?: null,
        'isbn' => preg_replace('/[^0-9Xx]/', '', (string) ($d['isbn'] ?? '')) ?: null,
        'brand_id' => (int) ($d['brand_id'] ?? 0) ?: null,
        'primary_category_id' => $primary ?: null,
        'short_description' => mb_substr(trim((string) ($d['short_description'] ?? '')), 0, 500) ?: null,
        'description' => (string) ($d['description'] ?? '') ?: null,
        'keywords' => mb_substr(trim((string) ($d['keywords'] ?? '')), 0, 500) ?: null,
        'price' => $price,
        'sale_price' => $sale,
        'cost_price' => round(max(0, (float) ($d['cost_price'] ?? 0)), 2),
        'unit' => array_key_exists((string) ($d['unit'] ?? ''), units_list()) ? $d['unit'] : 'piece',
        'unit_value' => isset($d['unit_value']) && $d['unit_value'] !== '' ? (float) $d['unit_value'] : null,
        'weight_grams' => isset($d['weight_grams']) && $d['weight_grams'] !== '' ? (int) $d['weight_grams'] : null,
        'length_cm' => isset($d['length_cm']) && $d['length_cm'] !== '' ? (float) $d['length_cm'] : null,
        'width_cm' => isset($d['width_cm']) && $d['width_cm'] !== '' ? (float) $d['width_cm'] : null,
        'height_cm' => isset($d['height_cm']) && $d['height_cm'] !== '' ? (float) $d['height_cm'] : null,
        'low_stock_threshold' => max(0, (int) ($d['low_stock_threshold'] ?? 5)),
        'min_order_qty' => max(1, (int) ($d['min_order_qty'] ?? 1)),
        'max_order_qty' => !empty($d['max_order_qty']) ? max(1, (int) $d['max_order_qty']) : null,
        'track_stock' => (int) !empty($d['track_stock']),
        'is_featured' => (int) !empty($d['is_featured']),
        'is_bestseller' => (int) !empty($d['is_bestseller']),
        'is_rashan_item' => (int) !empty($d['is_rashan_item']),
        'status' => in_array($d['status'] ?? '', ['active', 'inactive', 'archived'], true) ? $d['status'] : 'active',
    ];
    if ($row['max_order_qty'] && $row['max_order_qty'] < $row['min_order_qty']) return 'Maximum order quantity cannot be less than the minimum.';
    return db_tx(function () use ($id, $row, $cats, $d, $old) {
        if ($id) {
            db_update_row('products', $row, 'id = ?', [$id]);
            if ($old && $old['slug'] !== $row['slug']) seo_add_redirect('/product/' . $old['slug'] . '/', '/product/' . $row['slug'] . '/');
        } else {
            $id = db_insert_row('products', $row);
        }
        db_exec('DELETE FROM product_categories WHERE product_id = ?', [$id]);
        foreach ($cats as $c) db_exec('INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)', [$id, $c]);
        if (array_key_exists('tags', $d)) {
            db_exec('DELETE FROM product_tags WHERE product_id = ?', [$id]);
            foreach (array_unique(array_filter(array_map(function ($t) { return mb_substr(mb_strtolower(trim($t)), 0, 80); }, explode(',', (string) $d['tags'])))) as $t) {
                db_exec('INSERT IGNORE INTO product_tags (product_id, tag) VALUES (?, ?)', [$id, $t]);
            }
        }
        return $id;
    });
}
