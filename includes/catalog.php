<?php
/**
 * Catalogue queries: categories, collections, product listings, product detail data.
 */

// ---------------------------------------------------------------------
//  Categories
// ---------------------------------------------------------------------
function categories_all(bool $activeOnly = true): array
{
    static $cache = [];
    $k = (int) $activeOnly;
    if (!isset($cache[$k])) {
        $rows = db_all('SELECT * FROM categories' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, name');
        $cache[$k] = [];
        foreach ($rows as $r) {
            $cache[$k][(int) $r['id']] = $r;
        }
    }
    return $cache[$k];
}

/** Nested tree: each node gets a 'children' array. */
function category_tree(bool $activeOnly = true, ?int $parentId = null): array
{
    $all = categories_all($activeOnly);
    $out = [];
    foreach ($all as $c) {
        $pid = $c['parent_id'] !== null ? (int) $c['parent_id'] : null;
        if ($pid === $parentId) {
            $c['children'] = category_tree($activeOnly, (int) $c['id']);
            $out[] = $c;
        }
    }
    return $out;
}

/** Flat list with depth for <select> menus. */
function category_options(bool $activeOnly = false, ?array $tree = null, int $depth = 0): array
{
    $tree = $tree ?? category_tree($activeOnly);
    $out = [];
    foreach ($tree as $c) {
        $out[] = ['id' => (int) $c['id'], 'name' => $c['name'], 'depth' => $depth, 'label' => str_repeat('— ', $depth) . $c['name']];
        $out = array_merge($out, category_options($activeOnly, $c['children'], $depth + 1));
    }
    return $out;
}

function category_descendant_ids(int $id, bool $activeOnly = true): array
{
    $all = categories_all($activeOnly);
    $ids = [$id];
    $queue = [$id];
    while ($queue) {
        $cur = array_shift($queue);
        foreach ($all as $c) {
            if ((int) $c['parent_id'] === $cur && !in_array((int) $c['id'], $ids, true)) {
                $ids[] = (int) $c['id'];
                $queue[] = (int) $c['id'];
            }
        }
    }
    return $ids;
}

function category_by_slug(string $slug): ?array
{
    foreach (categories_all() as $c) {
        if ($c['slug'] === $slug) {
            return $c;
        }
    }
    return null;
}

function category_ancestors(array $cat): array
{
    $all = categories_all(false);
    $chain = [];
    $guard = 0;
    while ($cat && $cat['parent_id'] && isset($all[(int) $cat['parent_id']]) && $guard++ < 20) {
        $cat = $all[(int) $cat['parent_id']];
        array_unshift($chain, $cat);
    }
    return $chain;
}

function category_children(int $id): array
{
    return array_values(array_filter(categories_all(), fn($c) => (int) $c['parent_id'] === $id));
}

function category_url(array $c): string
{
    return url('category/' . $c['slug']);
}

// ---------------------------------------------------------------------
//  Collections
// ---------------------------------------------------------------------
function collection_by_slug(string $slug): ?array
{
    return db_row('SELECT * FROM collections WHERE slug = ? AND is_active = 1', [$slug]);
}

function collections_list(bool $homeOnly = false, int $limit = 50, array $ids = []): array
{
    if ($ids) {
        $rows = db_all('SELECT * FROM collections WHERE is_active = 1 AND id IN (' . db_in($ids) . ')', array_map('intval', $ids));
        $order = array_flip(array_map('intval', $ids));
        usort($rows, fn($a, $b) => ($order[(int) $a['id']] ?? 0) <=> ($order[(int) $b['id']] ?? 0));
        return array_slice($rows, 0, $limit);
    }
    return db_all('SELECT * FROM collections WHERE is_active = 1' . ($homeOnly ? ' AND show_on_home = 1' : '') . ' ORDER BY sort_order, name LIMIT ' . (int) $limit);
}

function collection_url(array $c): string
{
    return url('collections/' . $c['slug']);
}

// ---------------------------------------------------------------------
//  Product pricing & stock
// ---------------------------------------------------------------------
/** Returns ['regular' => x, 'sale' => y|null, 'final' => z, 'on_sale' => bool]. */
function price_info(array $product, ?array $variant = null): array
{
    $regular = (float) $product['price'];
    $sale = $product['sale_price'] !== null ? (float) $product['sale_price'] : null;
    if ($variant) {
        if ($variant['price'] !== null && (float) $variant['price'] > 0) {
            $regular = (float) $variant['price'];
            $sale = null;
        }
        if ($variant['sale_price'] !== null && (float) $variant['sale_price'] > 0) {
            $sale = (float) $variant['sale_price'];
        }
    }
    $onSale = $sale !== null && $sale > 0 && $sale < $regular;
    return ['regular' => $regular, 'sale' => $onSale ? $sale : null, 'final' => $onSale ? $sale : $regular, 'on_sale' => $onSale];
}

/** Quantity that can still be sold. PHP_INT_MAX when stock is not tracked or backorders are allowed. */
function available_qty(array $product, ?array $variant = null): int
{
    if (!(int) $product['track_stock'] || (int) $product['allow_backorder'] || (int) $product['made_to_order']) {
        return PHP_INT_MAX;
    }
    $qty = $variant ? (int) $variant['stock_qty'] : (int) $product['stock_qty'];
    return max(0, $qty);
}

/** SQL fragment: final selling price of a product row aliased "p". */
function sql_final_price(): string
{
    return '(CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price THEN p.sale_price ELSE p.price END)';
}

/** SQL fragment: product row "p" can be bought. */
function sql_in_stock(): string
{
    return '(p.track_stock = 0 OR p.allow_backorder = 1 OR p.made_to_order = 1
        OR (p.has_variants = 0 AND p.stock_qty > 0)
        OR (p.has_variants = 1 AND EXISTS (SELECT 1 FROM product_variants sv WHERE sv.product_id = p.id AND sv.is_active = 1 AND sv.stock_qty > 0)))';
}

// ---------------------------------------------------------------------
//  Product listings
// ---------------------------------------------------------------------
function product_sort_options(): array
{
    return [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'bestselling' => 'Best Selling',
        'rating' => 'Top Rated',
        'name' => 'Name A–Z',
    ];
}

/** Option names treated as "size" in filters. */
function size_option_names(): array
{
    return ['Size', 'Waist', 'Shoe Size', 'Length'];
}

/** Attribute columns available as listing filters: column => label. */
function filter_attribute_columns(): array
{
    return ['fit' => 'Fit', 'fabric' => 'Fabric', 'pattern' => 'Pattern', 'occasion' => 'Occasion', 'season' => 'Season', 'brand' => 'Brand'];
}

/**
 * Searches published products.
 * $f keys: category_ids[], collection_id, q, min_price, max_price, sizes[], colors[],
 *          fit[], fabric[], pattern[], occasion[], season[], brand[], in_stock, on_sale, is_new, ids[]
 * Returns ['items' => cards[], 'total' => n].
 */
function products_search(array $f, string $sort = 'featured', int $limit = 24, int $offset = 0): array
{
    $where = ["p.status = 'published'"];
    $params = [];
    $join = '';

    if (!empty($f['category_ids'])) {
        $where[] = 'p.category_id IN (' . db_in($f['category_ids']) . ')';
        $params = array_merge($params, array_map('intval', $f['category_ids']));
    }
    if (!empty($f['collection_id'])) {
        $join .= ' JOIN collection_products cp ON cp.product_id = p.id AND cp.collection_id = ' . (int) $f['collection_id'];
    }
    if (!empty($f['ids'])) {
        $where[] = 'p.id IN (' . db_in($f['ids']) . ')';
        $params = array_merge($params, array_map('intval', $f['ids']));
    }
    if (!empty($f['q'])) {
        $q = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['q']) . '%';
        $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ? OR p.fabric LIKE ? OR EXISTS (SELECT 1 FROM categories sc WHERE sc.id = p.category_id AND sc.name LIKE ?))';
        array_push($params, $q, $q, $q, $q, $q);
    }
    if (isset($f['min_price']) && $f['min_price'] !== null) {
        $where[] = sql_final_price() . ' >= ?';
        $params[] = (float) $f['min_price'];
    }
    if (isset($f['max_price']) && $f['max_price'] !== null) {
        $where[] = sql_final_price() . ' <= ?';
        $params[] = (float) $f['max_price'];
    }
    foreach (['sizes' => size_option_names(), 'colors' => ['Color']] as $key => $names) {
        if (!empty($f[$key])) {
            $where[] = 'EXISTS (SELECT 1 FROM product_variants fv JOIN product_variant_values fvv ON fvv.variant_id = fv.id
                WHERE fv.product_id = p.id AND fv.is_active = 1 AND fvv.option_name IN (' . db_in($names) . ') AND fvv.option_value IN (' . db_in($f[$key]) . '))';
            $params = array_merge($params, $names, array_values($f[$key]));
        }
    }
    foreach (array_keys(filter_attribute_columns()) as $col) {
        if (!empty($f[$col])) {
            $where[] = 'p.' . $col . ' IN (' . db_in($f[$col]) . ')';
            $params = array_merge($params, array_values($f[$col]));
        }
    }
    if (!empty($f['in_stock'])) {
        $where[] = sql_in_stock();
    }
    if (!empty($f['on_sale'])) {
        $where[] = '(p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price)';
    }
    if (!empty($f['is_new'])) {
        $where[] = '(p.is_new = 1 OR p.published_at >= DATE_SUB(NOW(), INTERVAL ' . max(1, (int) setting('new_arrival_days', 45)) . ' DAY))';
    }

    $orderBy = match ($sort) {
        'newest' => 'COALESCE(p.published_at, p.created_at) DESC, p.id DESC',
        'price_asc' => sql_final_price() . ' ASC, p.id DESC',
        'price_desc' => sql_final_price() . ' DESC, p.id DESC',
        'bestselling' => 'sold DESC, p.is_bestseller DESC, p.id DESC',
        'rating' => 'p.rating_avg DESC, p.rating_count DESC, p.id DESC',
        'name' => 'p.name ASC',
        default => (!empty($f['collection_id']) ? 'cp.sort_order ASC, ' : '') . 'p.is_featured DESC, COALESCE(p.published_at, p.created_at) DESC, p.id DESC',
    };
    $soldSelect = $sort === 'bestselling'
        ? ', (SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND o.status NOT IN (\'cancelled\',\'refunded\')) AS sold'
        : '';

    $whereSql = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(DISTINCT p.id) FROM products p $join WHERE $whereSql", $params);
    $rows = db_all("SELECT p.* $soldSelect FROM products p $join WHERE $whereSql ORDER BY $orderBy LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset, $params);
    return ['items' => hydrate_cards($rows), 'total' => $total];
}

/** Products by id, keeping the given order. */
function products_by_ids(array $ids, int $limit = 50): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $rows = db_all("SELECT p.* FROM products p WHERE p.status = 'published' AND p.id IN (" . db_in($ids) . ')', $ids);
    $pos = array_flip($ids);
    usort($rows, fn($a, $b) => $pos[(int) $a['id']] <=> $pos[(int) $b['id']]);
    return hydrate_cards(array_slice($rows, 0, $limit));
}

/**
 * Adds card display data (images, colours, stock, price info) to product rows
 * using a fixed number of queries regardless of how many products there are.
 */
function hydrate_cards(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_map(fn($r) => (int) $r['id'], $rows);
    $in = db_in($ids);

    $images = [];
    foreach (db_all("SELECT product_id, image, alt_text FROM product_images WHERE product_id IN ($in) ORDER BY is_main DESC, sort_order, id", $ids) as $img) {
        $images[(int) $img['product_id']][] = $img;
    }
    $colors = [];
    foreach (db_all(
        "SELECT DISTINCT v.product_id, vv.option_value AS name, c.hex_code
         FROM product_variants v JOIN product_variant_values vv ON vv.variant_id = v.id AND vv.option_name = 'Color'
         LEFT JOIN colors c ON c.name = vv.option_value
         WHERE v.product_id IN ($in) AND v.is_active = 1 ORDER BY vv.sort_order, vv.option_value",
        $ids
    ) as $c) {
        $colors[(int) $c['product_id']][$c['name']] = $c['hex_code'] ?: '#cccccc';
    }
    $variantStock = [];
    foreach (db_all("SELECT product_id, SUM(GREATEST(stock_qty,0)) AS qty, COUNT(*) AS n FROM product_variants WHERE product_id IN ($in) AND is_active = 1 GROUP BY product_id", $ids) as $v) {
        $variantStock[(int) $v['product_id']] = (int) $v['qty'];
    }
    $wish = wishlist_product_ids();

    foreach ($rows as &$p) {
        $id = (int) $p['id'];
        $imgs = $images[$id] ?? [];
        $p['image'] = $imgs[0]['image'] ?? null;
        $p['image_alt'] = $imgs[0]['alt_text'] ?? $p['name'];
        $p['hover_image'] = $imgs[1]['image'] ?? null;
        $p['colors'] = $colors[$id] ?? [];
        $p['price_info'] = price_info($p);
        $stock = (int) $p['has_variants'] ? ($variantStock[$id] ?? 0) : (int) $p['stock_qty'];
        $unlimited = !(int) $p['track_stock'] || (int) $p['allow_backorder'] || (int) $p['made_to_order'];
        $p['in_stock'] = $unlimited || $stock > 0;
        $p['low_stock'] = !$unlimited && $stock > 0 && $stock <= (int) $p['low_stock_threshold'];
        $p['badge_label'] = product_badge($p);
        $p['url'] = url('product/' . $p['slug']);
        $p['in_wishlist'] = in_array($id, $wish, true);
    }
    return $rows;
}

/** The single badge shown on a product card, in priority order. */
function product_badge(array $p): ?string
{
    if (!empty($p['badge'])) {
        return $p['badge'];
    }
    if (isset($p['in_stock']) && !$p['in_stock']) {
        return null;
    }
    if (!empty($p['price_info']['on_sale'])) {
        return 'Sale';
    }
    if ((int) $p['is_bestseller']) {
        return 'Best Seller';
    }
    if ((int) $p['is_new']) {
        return 'New Arrival';
    }
    return null;
}

function badge_class(string $badge): string
{
    return 'badge-' . slugify($badge);
}

/** Distinct filter values within the given category scope. */
function listing_facets(array $scope): array
{
    $where = "p.status = 'published'";
    $params = [];
    $join = '';
    if (!empty($scope['category_ids'])) {
        $where .= ' AND p.category_id IN (' . db_in($scope['category_ids']) . ')';
        $params = array_map('intval', $scope['category_ids']);
    }
    if (!empty($scope['collection_id'])) {
        $join = ' JOIN collection_products cp ON cp.product_id = p.id AND cp.collection_id = ' . (int) $scope['collection_id'];
    }
    $facets = [];
    foreach (filter_attribute_columns() as $col => $label) {
        $vals = db_col("SELECT DISTINCT p.$col FROM products p $join WHERE $where AND p.$col IS NOT NULL AND p.$col <> '' ORDER BY p.$col", $params);
        if ($vals) {
            $facets[$col] = ['label' => $label, 'values' => $vals];
        }
    }
    $names = size_option_names();
    $sizeRows = db_all(
        "SELECT vv.option_value, MIN(vv.sort_order) so, MIN(COALESCE(s.sort_order, 999)) sso
         FROM products p $join JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
         JOIN product_variant_values vv ON vv.variant_id = v.id AND vv.option_name IN (" . db_in($names) . ")
         LEFT JOIN sizes s ON s.name = vv.option_value
         WHERE $where GROUP BY vv.option_value ORDER BY sso, so, vv.option_value",
        array_merge($names, $params)
    );
    $facets['sizes'] = array_column($sizeRows, 'option_value');
    $facets['colors'] = db_all(
        "SELECT DISTINCT vv.option_value AS name, COALESCE(c.hex_code, '#cccccc') AS hex
         FROM products p $join JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
         JOIN product_variant_values vv ON vv.variant_id = v.id AND vv.option_name = 'Color'
         LEFT JOIN colors c ON c.name = vv.option_value WHERE $where ORDER BY vv.option_value",
        $params
    );
    $range = db_row('SELECT MIN(' . sql_final_price() . ') mn, MAX(' . sql_final_price() . ") mx FROM products p $join WHERE $where", $params);
    $facets['price'] = ['min' => (int) floor((float) ($range['mn'] ?? 0)), 'max' => (int) ceil((float) ($range['mx'] ?? 0))];
    return $facets;
}

/** Reads listing filters from the query string, validated. */
function listing_filters_from_request(): array
{
    $list = function (string $k): array {
        $v = $_GET[$k] ?? [];
        $v = is_array($v) ? $v : explode(',', (string) $v);
        return array_values(array_filter(array_map(fn($x) => substr(trim((string) $x), 0, 80), $v), fn($x) => $x !== ''));
    };
    $f = [
        'q' => substr(input('q', '', 'get'), 0, 100),
        'min_price' => input_float('min_price'),
        'max_price' => input_float('max_price'),
        'sizes' => $list('size'),
        'colors' => $list('color'),
        'in_stock' => !empty($_GET['in_stock']),
        'on_sale' => !empty($_GET['on_sale']),
    ];
    foreach (array_keys(filter_attribute_columns()) as $col) {
        $f[$col] = $list($col);
    }
    if (!empty($_GET['subcategory'])) {
        $f['subcategory'] = (int) $_GET['subcategory'];
    }
    return $f;
}

// ---------------------------------------------------------------------
//  Home merchandising
// ---------------------------------------------------------------------
function new_arrivals(int $limit = 12): array
{
    $rows = db_all(
        "SELECT p.* FROM products p WHERE p.status = 'published'
         ORDER BY p.is_new DESC, COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT " . (int) $limit
    );
    return hydrate_cards($rows);
}

/**
 * $mode: units (all non-cancelled orders), completed (delivered orders only), flag (is_bestseller)
 * Falls back to flagged products when there is not enough sales history yet.
 */
function best_sellers(int $limit = 12, string $mode = 'units'): array
{
    $ids = [];
    if ($mode !== 'flag') {
        $statusSql = $mode === 'completed' ? "o.status = 'delivered'" : "o.status NOT IN ('cancelled','refunded')";
        $ids = db_col(
            "SELECT oi.product_id FROM order_items oi JOIN orders o ON o.id = oi.order_id
             JOIN products p ON p.id = oi.product_id AND p.status = 'published'
             WHERE $statusSql GROUP BY oi.product_id ORDER BY SUM(oi.qty) DESC LIMIT " . (int) $limit
        );
    }
    if (count($ids) < $limit) {
        $more = db_col(
            "SELECT id FROM products WHERE status = 'published' AND is_bestseller = 1 ORDER BY rating_count DESC, id DESC LIMIT " . (int) $limit
        );
        $ids = array_values(array_unique(array_merge($ids, $more)));
    }
    return products_by_ids($ids, $limit);
}

// ---------------------------------------------------------------------
//  Product detail
// ---------------------------------------------------------------------
function product_by_slug(string $slug, bool $includeUnpublished = false): ?array
{
    return db_row('SELECT * FROM products WHERE slug = ?' . ($includeUnpublished ? '' : " AND status = 'published'"), [$slug]);
}

function product_images(int $productId): array
{
    return db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id', [$productId]);
}

/** Variants with their option values: [['id'=>..,'options'=>['Size'=>'M','Color'=>'Black'],...]] */
function product_variants(int $productId, bool $activeOnly = true): array
{
    $vars = db_all('SELECT * FROM product_variants WHERE product_id = ?' . ($activeOnly ? ' AND is_active = 1' : '') . ' ORDER BY sort_order, id', [$productId]);
    if (!$vars) {
        return [];
    }
    $ids = array_column($vars, 'id');
    $vals = [];
    foreach (db_all('SELECT * FROM product_variant_values WHERE variant_id IN (' . db_in($ids) . ') ORDER BY sort_order, id', array_map('intval', $ids)) as $v) {
        $vals[(int) $v['variant_id']][$v['option_name']] = $v['option_value'];
    }
    foreach ($vars as &$v) {
        $v['options'] = $vals[(int) $v['id']] ?? [];
    }
    return $vars;
}

function variant_label(array $options): string
{
    return implode(' / ', array_map(fn($k, $v) => $k . ': ' . $v, array_keys($options), $options));
}

/** Option groups in display order: ['Color' => ['Black','White'], 'Size' => ['S','M']] */
function variant_option_groups(array $variants): array
{
    $groups = [];
    foreach ($variants as $v) {
        foreach ($v['options'] as $name => $value) {
            $groups[$name][$value] = true;
        }
    }
    $sizeOrder = array_flip(db_col('SELECT name FROM sizes ORDER BY sort_order, id'));
    foreach ($groups as $name => &$vals) {
        $vals = array_keys($vals);
        if ($name !== 'Color') {
            usort($vals, function ($a, $b) use ($sizeOrder) {
                if (is_numeric($a) && is_numeric($b)) {
                    return $a <=> $b;
                }
                $sa = $sizeOrder[$a] ?? null;
                $sb = $sizeOrder[$b] ?? null;
                if ($sa !== null && $sb !== null) {
                    return $sa <=> $sb;
                }
                return is_numeric($a) && is_numeric($b) ? $a <=> $b : strnatcmp($a, $b);
            });
        }
    }
    uksort($groups, fn($a, $b) => ($a === 'Color' ? 0 : 1) <=> ($b === 'Color' ? 0 : 1));
    return $groups;
}

function product_attributes(int $productId): array
{
    return db_all('SELECT name, value FROM product_attributes WHERE product_id = ? ORDER BY sort_order, id', [$productId]);
}

function product_relations(int $productId, string $type, int $limit = 8): array
{
    $ids = db_col('SELECT related_id FROM product_relations WHERE product_id = ? AND relation_type = ? ORDER BY sort_order', [$productId, $type]);
    return products_by_ids($ids, $limit);
}

function related_products(array $product, int $limit = 8): array
{
    $items = product_relations((int) $product['id'], 'related', $limit);
    if (count($items) < $limit && $product['category_id']) {
        $exclude = array_merge([(int) $product['id']], array_map(fn($i) => (int) $i['id'], $items));
        $rows = db_all(
            "SELECT p.* FROM products p WHERE p.status = 'published' AND p.category_id = ? AND p.id NOT IN (" . db_in($exclude) . ')
             ORDER BY p.is_featured DESC, p.id DESC LIMIT ' . ($limit - count($items)),
            array_merge([(int) $product['category_id']], $exclude)
        );
        $items = array_merge($items, hydrate_cards($rows));
    }
    return $items;
}

/** Products most often bought in the same order, falling back to cross-sells. */
function frequently_bought_together(int $productId, int $limit = 3): array
{
    $ids = db_col(
        "SELECT oi2.product_id FROM order_items oi1
         JOIN order_items oi2 ON oi2.order_id = oi1.order_id AND oi2.product_id <> oi1.product_id
         JOIN orders o ON o.id = oi1.order_id AND o.status NOT IN ('cancelled','refunded')
         WHERE oi1.product_id = ? AND oi2.product_id IS NOT NULL GROUP BY oi2.product_id ORDER BY COUNT(*) DESC LIMIT " . (int) $limit,
        [$productId]
    );
    $items = products_by_ids($ids, $limit);
    if (count($items) < $limit) {
        $have = array_map(fn($i) => (int) $i['id'], $items);
        foreach (product_relations($productId, 'cross_sell', $limit) as $p) {
            if (!in_array((int) $p['id'], $have, true) && count($items) < $limit) {
                $items[] = $p;
            }
        }
    }
    return $items;
}

// ---------------------------------------------------------------------
//  Recently viewed (cookie, ids only)
// ---------------------------------------------------------------------
function recently_viewed_ids(): array
{
    $raw = $_COOKIE['uc_rv'] ?? '';
    return array_slice(array_values(array_filter(array_map('intval', explode('.', $raw)))), 0, 12);
}

function recently_viewed_track(int $productId): void
{
    $ids = array_values(array_diff(recently_viewed_ids(), [$productId]));
    array_unshift($ids, $productId);
    if (!headers_sent()) {
        setcookie('uc_rv', implode('.', array_slice($ids, 0, 12)), [
            'expires' => time() + 86400 * 60, 'path' => (BASE_PATH ?: '') . '/', 'secure' => uc_is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
}

// ---------------------------------------------------------------------
//  Reviews
// ---------------------------------------------------------------------
function product_reviews(int $productId, int $limit = 20): array
{
    return db_all("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT " . (int) $limit, [$productId]);
}

function review_breakdown(int $productId): array
{
    $out = array_fill(1, 5, 0);
    foreach (db_all("SELECT rating, COUNT(*) n FROM reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating", [$productId]) as $r) {
        $out[(int) $r['rating']] = (int) $r['n'];
    }
    return $out;
}

function product_rating_refresh(int $productId): void
{
    db_exec(
        "UPDATE products SET rating_avg = COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = ? AND status = 'approved'), 0),
         rating_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = 'approved') WHERE id = ?",
        [$productId, $productId, $productId]
    );
}

function customer_purchased_product(int $customerId, int $productId): bool
{
    return (bool) db_val(
        "SELECT 1 FROM order_items oi JOIN orders o ON o.id = oi.order_id
         WHERE o.customer_id = ? AND oi.product_id = ? AND o.status IN ('delivered','shipped','processing','confirmed') LIMIT 1",
        [$customerId, $productId]
    );
}

// ---------------------------------------------------------------------
//  Wishlist (guest token cookie or customer account)
// ---------------------------------------------------------------------
function wishlist_id(bool $create = false): ?int
{
    $cid = customer_id();
    if ($cid) {
        $id = db_val('SELECT id FROM wishlists WHERE customer_id = ?', [$cid]);
        if (!$id && $create) {
            $id = db_insert('wishlists', ['customer_id' => $cid]);
        }
        return $id ? (int) $id : null;
    }
    $token = $_COOKIE['uc_wish'] ?? '';
    if ($token && preg_match('/^[a-f0-9]{48}$/', $token)) {
        $id = db_val('SELECT id FROM wishlists WHERE token = ? AND customer_id IS NULL', [$token]);
        if ($id) {
            return (int) $id;
        }
    }
    if (!$create) {
        return null;
    }
    $token = random_token(24);
    $id = db_insert('wishlists', ['token' => $token]);
    setcookie('uc_wish', $token, ['expires' => time() + 86400 * 90, 'path' => (BASE_PATH ?: '') . '/', 'secure' => uc_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE['uc_wish'] = $token;
    return $id;
}

function wishlist_product_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $wid = wishlist_id();
        $ids = $wid ? array_map('intval', db_col('SELECT product_id FROM wishlist_items WHERE wishlist_id = ?', [$wid])) : [];
    }
    return $ids;
}

/** Toggles a product; returns true when it is now in the wishlist. */
function wishlist_toggle(int $productId): bool
{
    $wid = wishlist_id(true);
    if (db_val('SELECT 1 FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wid, $productId])) {
        db_exec('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wid, $productId]);
        return false;
    }
    db_exec('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)', [$wid, $productId]);
    return true;
}

function wishlist_merge_guest(int $customerId, ?string $token): void
{
    if (!$token || !preg_match('/^[a-f0-9]{48}$/', $token)) {
        return;
    }
    $guest = db_val('SELECT id FROM wishlists WHERE token = ? AND customer_id IS NULL', [$token]);
    if (!$guest) {
        return;
    }
    $mine = db_val('SELECT id FROM wishlists WHERE customer_id = ?', [$customerId]);
    if (!$mine) {
        db_exec('UPDATE wishlists SET customer_id = ?, token = NULL WHERE id = ?', [$customerId, (int) $guest]);
        return;
    }
    db_exec('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) SELECT ?, product_id FROM wishlist_items WHERE wishlist_id = ?', [(int) $mine, (int) $guest]);
    db_exec('DELETE FROM wishlists WHERE id = ?', [(int) $guest]);
}
