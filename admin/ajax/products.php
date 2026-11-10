<?php
/**
 * Admin product lookup (POS, purchase entry, pickers).
 * GET q= (name/SKU/barcode/ISBN), returns sellable rows (one per variation).
 * Exact barcode/SKU match returns `exact` for instant scanner adds.
 */
require dirname(__DIR__) . '/includes/init.php';
if (!admin_can('pos') && !admin_can('products') && !admin_can('inventory') && !admin_can('orders') && !admin_can('content')) json_error('Forbidden', 403);
$q = trim(input('q'));
$limit = max(1, min(60, input_int('limit', 24)));
$includeInactive = input('all') === '1';
if ($q === '') {
    $ids = db_col("SELECT id FROM products WHERE status = 'active' AND deleted_at IS NULL ORDER BY sales_count DESC, id DESC LIMIT $limit");
} else {
    // exact code (scanner)
    $exact = db_row("SELECT p.id AS pid, COALESCE(v.id, 0) AS vid FROM products p LEFT JOIN product_variations v ON v.product_id = p.id AND (v.barcode = ? OR v.sku = ?)
        WHERE p.deleted_at IS NULL AND (p.barcode = ? OR p.sku = ? OR p.isbn = ? OR p.product_code = ? OR v.id IS NOT NULL) LIMIT 1", [$q, $q, $q, $q, $q, $q]);
    $ids = $exact ? [(int) $exact['pid']] : search_product_ids($q, ['limit' => $limit]);
    if (!$ids && $includeInactive) {
        $ids = db_col("SELECT id FROM products WHERE deleted_at IS NULL AND (name LIKE ? OR sku LIKE ?) LIMIT $limit", ['%' . db_like($q) . '%', '%' . db_like($q) . '%']);
    }
}
$out = [];
foreach (array_slice($ids, 0, $limit) as $pid) {
    $p = db_row('SELECT id, name, has_variations, status FROM products WHERE id = ?', [$pid]);
    if (!$p) continue;
    $vids = $p['has_variations'] ? db_col('SELECT id FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY sort_order, id', [$pid]) : [0];
    foreach ($vids as $vid) {
        if (isset($exact) && $exact && (int) $exact['vid'] && (int) $exact['vid'] !== (int) $vid) continue;
        $it = sellable_item((int) $pid, (int) $vid, true);
        if (!$it) continue;
        $out[] = ['product_id' => $it['product_id'], 'variation_id' => $it['variation_id'], 'label' => $it['name'] . ($it['variation_name'] ? ' - ' . $it['variation_name'] : ''),
            'sku' => $it['sku'], 'unit' => $it['unit'], 'unit_price' => $it['unit_price'], 'price' => $it['price'], 'cost_price' => $it['cost_price'],
            'stock' => $it['track_stock'] ? $it['stock'] : '∞', 'stock_num' => $it['track_stock'] ? $it['stock'] : 99999, 'thumb' => upload_url($it['thumb']), 'active' => $p['status'] === 'active'];
    }
}
json_out(['ok' => true, 'items' => $out, 'exact' => !empty($exact) && count($out) === 1]);
