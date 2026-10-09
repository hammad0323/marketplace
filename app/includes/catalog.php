<?php
/**
 * Catalogue: categories, products, pricing, availability, search & filters.
 * All prices shown or charged are computed here from the database —
 * never from client input.
 */

// ---- Categories -------------------------------------------------------------

function categories_all(bool $activeOnly = true): array
{
    static $cache = [];
    $k = (int) $activeOnly;
    if (!isset($cache[$k])) {
        $cache[$k] = db_all('SELECT * FROM categories' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order, name');
    }
    return $cache[$k];
}

/** Nested tree: each node gets a 'children' array. */
function category_tree(bool $activeOnly = true): array
{
    $byParent = [];
    foreach (categories_all($activeOnly) as $c) {
        $byParent[(int) $c['parent_id']][] = $c;
    }
    $build = function ($parentId) use (&$build, $byParent) {
        $out = [];
        foreach ($byParent[$parentId] ?? [] as $c) {
            $c['children'] = $build((int) $c['id']);
            $out[] = $c;
        }
        return $out;
    };
    return $build(0);
}

function category_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]);
}

function category_by_id(int $id): ?array
{
    return db_one('SELECT * FROM categories WHERE id = ?', [$id]);
}

/** IDs of a category plus all active descendants. */
function category_descendant_ids(int $id): array
{
    $ids = [$id];
    $all = categories_all(true);
    $frontier = [$id];
    while ($frontier) {
        $next = [];
        foreach ($all as $c) {
            if (in_array((int) $c['parent_id'], $frontier, true)) {
                $ids[] = (int) $c['id'];
                $next[] = (int) $c['id'];
            }
        }
        $frontier = $next;
    }
    return array_values(array_unique($ids));
}

/** Ancestor chain (root first) for breadcrumbs. */
function category_ancestors(array $cat): array
{
    $chain = [$cat];
    $guard = 0;
    while (!empty($cat['parent_id']) && $guard++ < 10) {
        $cat = category_by_id((int) $cat['parent_id']);
        if (!$cat) {
            break;
        }
        array_unshift($chain, $cat);
    }
    return $chain;
}

// ---- Product query building -------------------------------------------------------

/** SQL fragment for a product's effective (sale-aware) price. */
function sql_effective_price(string $alias = 'p'): string
{
    return "(CASE WHEN $alias.sale_price IS NOT NULL AND $alias.sale_price > 0 AND $alias.sale_price < $alias.regular_price THEN $alias.sale_price ELSE $alias.regular_price END)";
}

/** Orders counted as "completed" for best-seller ranking. */
function sql_completed_orders(string $alias = 'o'): string
{
    return "($alias.status IN ('shipped','delivered') AND $alias.payment_status NOT IN ('refunded','failed','cancelled'))";
}

function product_select_sql(): string
{
    return 'SELECT p.*, c.name AS category_name, c.slug AS category_slug, ' . sql_effective_price() . ' AS effective_price,
            COALESCE(inv.qty, 0) AS stock_qty
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT i.product_id, SUM(GREATEST(i.quantity, 0)) AS qty
                FROM product_inventory i
                LEFT JOIN product_variants v ON v.id = i.variant_id
                WHERE i.variant_id IS NULL OR v.is_active = 1
                GROUP BY i.product_id
            ) inv ON inv.product_id = p.id';
}

/** Turn a free-text query into a safe BOOLEAN MODE expression. */
function fulltext_terms(string $q): string
{
    $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $q));
    $terms = [];
    foreach ($words as $w) {
        if (mb_strlen($w) >= 2) {
            $terms[] = '+' . $w . '*';
        }
    }
    return implode(' ', array_slice($terms, 0, 8));
}

/**
 * Filtered, sorted, paginated product listing.
 * $f keys: category_ids[], collection_id, q, min_price, max_price, colors[], materials[],
 *          types[], availability (in_stock|out_of_stock), sort, page, per_page, ids[]
 */
function products_query(array $f): array
{
    $where = ["p.status = 'published'", 'c.is_active = 1'];
    $params = [];
    $orderParams = [];

    if (!empty($f['category_ids'])) {
        $ids = array_map('intval', $f['category_ids']);
        $where[] = '(p.category_id IN (' . db_in($ids) . ') OR p.subcategory_id IN (' . db_in($ids) . '))';
        $params = array_merge($params, $ids, $ids);
    }
    if (!empty($f['collection_id'])) {
        $where[] = 'p.id IN (SELECT product_id FROM collection_products WHERE collection_id = ?)';
        $params[] = (int) $f['collection_id'];
    }
    $q = trim((string) ($f['q'] ?? ''));
    $relevance = '0';
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $ft = fulltext_terms($q);
        $cond = ['p.name LIKE ?', 'p.sku LIKE ?', 'c.name LIKE ?', 'p.material LIKE ?', 'p.wallet_type LIKE ?'];
        $condParams = [$like, $like, $like, $like, $like];
        if ($ft !== '') {
            $cond[] = 'MATCH(p.name, p.short_description, p.sku) AGAINST (? IN BOOLEAN MODE)';
            $condParams[] = $ft;
            $relevance = '(MATCH(p.name, p.short_description, p.sku) AGAINST (? IN BOOLEAN MODE) + (p.name LIKE ?) * 5 + (p.name LIKE ?) * 3)';
            $orderParams = [$ft, addcslashes($q, '%_\\') . '%', $like];
        } else {
            $relevance = '((p.name LIKE ?) * 5 + (p.name LIKE ?) * 3)';
            $orderParams = [addcslashes($q, '%_\\') . '%', $like];
        }
        $where[] = '(' . implode(' OR ', $cond) . ')';
        $params = array_merge($params, $condParams);
    }
    if (isset($f['min_price']) && $f['min_price'] !== '' && is_numeric($f['min_price'])) {
        $where[] = sql_effective_price() . ' >= ?';
        $params[] = (float) $f['min_price'];
    }
    if (isset($f['max_price']) && $f['max_price'] !== '' && is_numeric($f['max_price'])) {
        $where[] = sql_effective_price() . ' <= ?';
        $params[] = (float) $f['max_price'];
    }
    if (!empty($f['colors'])) {
        $colors = array_slice(array_map('strval', $f['colors']), 0, 20);
        $where[] = 'EXISTS (SELECT 1 FROM product_variants v2 WHERE v2.product_id = p.id AND v2.is_active = 1 AND v2.color_name IN (' . db_in($colors) . '))';
        $params = array_merge($params, $colors);
    }
    if (!empty($f['materials'])) {
        $m = array_slice(array_map('strval', $f['materials']), 0, 20);
        $where[] = 'p.material IN (' . db_in($m) . ')';
        $params = array_merge($params, $m);
    }
    if (!empty($f['types'])) {
        $t = array_slice(array_map('strval', $f['types']), 0, 20);
        $where[] = 'p.wallet_type IN (' . db_in($t) . ')';
        $params = array_merge($params, $t);
    }
    if (($f['availability'] ?? '') === 'in_stock') {
        $where[] = "((p.track_stock = 0 AND p.stock_status <> 'out_of_stock') OR (p.track_stock = 1 AND COALESCE(inv.qty, 0) > 0))";
    } elseif (($f['availability'] ?? '') === 'out_of_stock') {
        $where[] = "((p.track_stock = 0 AND p.stock_status = 'out_of_stock') OR (p.track_stock = 1 AND COALESCE(inv.qty, 0) <= 0))";
    }
    if (isset($f['ids'])) {
        $ids = array_map('intval', (array) $f['ids']) ?: [0];
        $where[] = 'p.id IN (' . db_in($ids) . ')';
        $params = array_merge($params, $ids);
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $base = product_select_sql();
    $total = (int) db_val('SELECT COUNT(*) FROM (' . $base . $whereSql . ') t', $params);

    $sort = $f['sort'] ?? ($q !== '' ? 'relevance' : 'featured');
    $soldSql = '(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = p.id AND ' . sql_completed_orders() . ')';
    switch ($sort) {
        case 'price_asc':
            $order = 'effective_price ASC, p.id DESC';
            $orderParams = [];
            break;
        case 'price_desc':
            $order = 'effective_price DESC, p.id DESC';
            $orderParams = [];
            break;
        case 'newest':
            $order = 'p.created_at DESC, p.id DESC';
            $orderParams = [];
            break;
        case 'popular':
            $order = "$soldSql DESC, p.view_count DESC, p.id DESC";
            $orderParams = [];
            break;
        case 'name':
            $order = 'p.name ASC';
            $orderParams = [];
            break;
        case 'relevance':
            $order = $q !== '' ? "$relevance DESC, p.is_featured DESC, p.id DESC" : 'p.is_featured DESC, p.created_at DESC';
            if ($q === '') {
                $orderParams = [];
            }
            break;
        default:
            $order = 'p.is_featured DESC, p.is_best_seller DESC, p.created_at DESC';
            $orderParams = [];
    }

    $perPage = max(1, min(60, (int) ($f['per_page'] ?? 12)));
    $pg = paginate($total, $perPage, (int) ($f['page'] ?? 1));
    $rows = db_all(
        $base . $whereSql . " ORDER BY $order LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'],
        array_merge($params, $orderParams)
    );
    return ['items' => hydrate_products($rows), 'pagination' => $pg, 'sort' => $sort];
}

/** Published products by id, preserving the given order. */
function products_by_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) {
        return [];
    }
    $rows = db_all(product_select_sql() . " WHERE p.status = 'published' AND p.id IN (" . db_in($ids) . ')', $ids);
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int) $r['id']] = $r;
    }
    $ordered = [];
    foreach ($ids as $id) {
        if (isset($byId[$id])) {
            $ordered[] = $byId[$id];
        }
    }
    return hydrate_products($ordered);
}

/** Attach main/hover images, colour swatches and rating summary to listing rows. */
function hydrate_products(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_map(fn($r) => (int) $r['id'], $rows);
    $in = db_in($ids);
    $images = [];
    foreach (db_all("SELECT product_id, file_path, alt_text FROM product_images WHERE product_id IN ($in) ORDER BY is_main DESC, sort_order, id", $ids) as $img) {
        $images[(int) $img['product_id']][] = $img;
    }
    $swatches = [];
    foreach (db_all("SELECT product_id, color_name, color_hex FROM product_variants WHERE is_active = 1 AND color_hex IS NOT NULL AND product_id IN ($in) ORDER BY sort_order, id", $ids) as $v) {
        $swatches[(int) $v['product_id']][] = $v;
    }
    $ratings = [];
    foreach (db_all("SELECT product_id, AVG(rating) avg_rating, COUNT(*) cnt FROM reviews WHERE status = 'approved' AND product_id IN ($in) GROUP BY product_id", $ids) as $r) {
        $ratings[(int) $r['product_id']] = $r;
    }
    $variantCounts = [];
    foreach (db_all("SELECT product_id, COUNT(*) n FROM product_variants WHERE is_active = 1 AND product_id IN ($in) GROUP BY product_id", $ids) as $v) {
        $variantCounts[(int) $v['product_id']] = (int) $v['n'];
    }
    foreach ($rows as &$r) {
        $id = (int) $r['id'];
        $imgs = $images[$id] ?? [];
        $r['image'] = $imgs[0]['file_path'] ?? null;
        $r['image_alt'] = $imgs[0]['alt_text'] ?? $r['name'];
        $r['hover_image'] = $imgs[1]['file_path'] ?? null;
        $r['swatches'] = $swatches[$id] ?? [];
        $r['avg_rating'] = isset($ratings[$id]) ? round((float) $ratings[$id]['avg_rating'], 1) : null;
        $r['review_count'] = isset($ratings[$id]) ? (int) $ratings[$id]['cnt'] : 0;
        $r['variant_count'] = $variantCounts[$id] ?? 0;
        $r['availability'] = product_availability($r);
    }
    return $rows;
}

/**
 * Availability for a product (or a specific variant quantity).
 * Returns: in_stock | low_stock | out_of_stock | preorder
 */
function product_availability(array $p, ?int $qty = null): string
{
    if (!(int) $p['track_stock']) {
        return $p['stock_status'];
    }
    $qty = $qty ?? (int) ($p['stock_qty'] ?? 0);
    if ($qty <= 0) {
        return 'out_of_stock';
    }
    return $qty <= (int) $p['low_stock_threshold'] ? 'low_stock' : 'in_stock';
}

function availability_label(string $a, ?int $qty = null): string
{
    switch ($a) {
        case 'low_stock':
            return $qty ? "Only $qty left" : 'Low stock';
        case 'out_of_stock':
            return 'Out of stock';
        case 'preorder':
            return 'Available to pre-order';
        default:
            return 'In stock';
    }
}

function is_purchasable(string $availability): bool
{
    return $availability !== 'out_of_stock';
}

function discount_percent(float $regular, float $sale): int
{
    if ($regular <= 0 || $sale <= 0 || $sale >= $regular) {
        return 0;
    }
    return (int) round(100 - ($sale / $regular * 100));
}

// ---- Single product ---------------------------------------------------------------

function product_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $row = db_one(product_select_sql() . ' WHERE p.slug = ?' . ($publishedOnly ? " AND p.status = 'published' AND c.is_active = 1" : ''), [$slug]);
    return $row ? hydrate_products([$row])[0] : null;
}

function product_by_id(int $id, bool $publishedOnly = true): ?array
{
    $row = db_one(product_select_sql() . ' WHERE p.id = ?' . ($publishedOnly ? " AND p.status = 'published'" : ''), [$id]);
    return $row ? hydrate_products([$row])[0] : null;
}

function product_images(int $productId): array
{
    return db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id', [$productId]);
}

/** Active variants with their stock and computed prices. */
function product_variants(int $productId, bool $activeOnly = true): array
{
    $rows = db_all(
        'SELECT v.*, COALESCE(i.quantity, 0) AS stock_qty, img.file_path AS image_path
         FROM product_variants v
         LEFT JOIN product_inventory i ON i.variant_id = v.id
         LEFT JOIN product_images img ON img.id = v.image_id
         WHERE v.product_id = ?' . ($activeOnly ? ' AND v.is_active = 1' : '') . ' ORDER BY v.sort_order, v.id',
        [$productId]
    );
    if ($rows) {
        $ids = array_map(fn($v) => (int) $v['id'], $rows);
        $attrs = [];
        foreach (db_all('SELECT * FROM product_variant_values WHERE variant_id IN (' . db_in($ids) . ') ORDER BY id', $ids) as $a) {
            $attrs[(int) $a['variant_id']][$a['attribute_name']] = $a['attribute_value'];
        }
        foreach ($rows as &$v) {
            $v['attributes'] = $attrs[(int) $v['id']] ?? [];
        }
    }
    return $rows;
}

/**
 * Authoritative unit pricing for a product/variant.
 * @return array{regular: float, price: float, on_sale: bool}
 */
function unit_pricing(array $product, ?array $variant = null): array
{
    $regular = (float) $product['regular_price'];
    $sale = $product['sale_price'] !== null ? (float) $product['sale_price'] : null;
    if ($variant) {
        if ($variant['price_override'] !== null && (float) $variant['price_override'] > 0) {
            $regular = (float) $variant['price_override'];
            $sale = null;
        }
        if ($variant['sale_override'] !== null && (float) $variant['sale_override'] > 0) {
            $sale = (float) $variant['sale_override'];
        }
    }
    $onSale = $sale !== null && $sale > 0 && $sale < $regular;
    return ['regular' => round($regular, 2), 'price' => round($onSale ? $sale : $regular, 2), 'on_sale' => $onSale];
}

/** Stock on hand for a sellable unit (product or variant). */
function unit_stock(int $productId, ?int $variantId): int
{
    if ($variantId) {
        return (int) db_val('SELECT quantity FROM product_inventory WHERE product_id = ? AND variant_id = ?', [$productId, $variantId]);
    }
    return (int) db_val('SELECT quantity FROM product_inventory WHERE product_id = ? AND variant_id IS NULL', [$productId]);
}

function product_relations(int $productId, string $type, int $limit = 8): array
{
    $ids = db_col('SELECT related_id FROM product_relations WHERE product_id = ? AND relation_type = ? ORDER BY sort_order LIMIT ' . (int) $limit, [$productId, $type]);
    return products_by_ids($ids);
}

/** Related products: explicit relations first, then same category. */
function related_products(array $product, int $limit = 8): array
{
    $items = product_relations((int) $product['id'], 'related', $limit);
    if (count($items) < $limit) {
        $exclude = array_merge([(int) $product['id']], array_map(fn($p) => (int) $p['id'], $items));
        $more = db_col(
            "SELECT id FROM products WHERE status = 'published' AND category_id = ? AND id NOT IN (" . db_in($exclude) . ') ORDER BY is_featured DESC, created_at DESC LIMIT ' . ($limit - count($items)),
            array_merge([(int) $product['category_id']], $exclude)
        );
        $items = array_merge($items, products_by_ids($more));
    }
    return $items;
}

function product_rating_summary(int $productId): array
{
    $row = db_one("SELECT AVG(rating) a, COUNT(*) n FROM reviews WHERE product_id = ? AND status = 'approved'", [$productId]);
    $dist = array_fill(1, 5, 0);
    foreach (db_all("SELECT rating, COUNT(*) n FROM reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating", [$productId]) as $r) {
        $dist[(int) $r['rating']] = (int) $r['n'];
    }
    return ['average' => $row['n'] ? round((float) $row['a'], 1) : 0, 'count' => (int) $row['n'], 'distribution' => $dist];
}

function product_reviews(int $productId, int $limit = 20): array
{
    return db_all("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT " . (int) $limit, [$productId]);
}

// ---- Merchandising --------------------------------------------------------------

function new_arrival_products(int $limit, string $source = 'flagged', array $manualIds = []): array
{
    if ($source === 'manual' && $manualIds) {
        return array_slice(products_by_ids($manualIds), 0, $limit);
    }
    $where = $source === 'flagged' ? 'AND p.is_new_arrival = 1' : '';
    $rows = db_all(product_select_sql() . " WHERE p.status = 'published' AND c.is_active = 1 $where ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT " . (int) $limit);
    if (!$rows && $where) {
        return new_arrival_products($limit, 'latest');
    }
    return hydrate_products($rows);
}

/**
 * Best sellers. 'auto' ranks by quantity sold on completed orders, topping up
 * with products flagged as best sellers when sales data is thin.
 */
function best_seller_products(int $limit, string $source = 'auto', array $manualIds = []): array
{
    if ($source === 'manual' && $manualIds) {
        return array_slice(products_by_ids($manualIds), 0, $limit);
    }
    $ids = [];
    if ($source === 'auto') {
        $ids = db_col(
            "SELECT oi.product_id FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id
             WHERE p.status = 'published' AND " . sql_completed_orders() . '
             GROUP BY oi.product_id ORDER BY SUM(oi.quantity) DESC LIMIT ' . (int) $limit
        );
    }
    if (count($ids) < $limit) {
        $flagged = db_col(
            "SELECT id FROM products WHERE status = 'published' AND is_best_seller = 1" . ($ids ? ' AND id NOT IN (' . db_in($ids) . ')' : '') . ' ORDER BY is_featured DESC, created_at DESC LIMIT ' . ($limit - count($ids)),
            $ids
        );
        $ids = array_merge($ids, $flagged);
    }
    return products_by_ids($ids);
}

function featured_products(int $limit): array
{
    $ids = db_col("SELECT id FROM products WHERE status = 'published' AND is_featured = 1 ORDER BY created_at DESC LIMIT " . (int) $limit);
    return products_by_ids($ids);
}

/** Facet values available within a category scope. */
function product_facets(array $categoryIds = [], ?int $collectionId = null): array
{
    $where = "p.status = 'published'";
    $params = [];
    if ($categoryIds) {
        $where .= ' AND (p.category_id IN (' . db_in($categoryIds) . ') OR p.subcategory_id IN (' . db_in($categoryIds) . '))';
        $params = array_merge($categoryIds, $categoryIds);
    }
    if ($collectionId) {
        $where .= ' AND p.id IN (SELECT product_id FROM collection_products WHERE collection_id = ?)';
        $params[] = $collectionId;
    }
    return [
        'colors' => db_all("SELECT v.color_name, MAX(v.color_hex) color_hex, COUNT(DISTINCT p.id) n FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.is_active = 1 AND v.color_name IS NOT NULL AND v.color_name <> '' AND $where GROUP BY v.color_name ORDER BY v.color_name", $params),
        'materials' => db_all("SELECT p.material AS value, COUNT(*) n FROM products p WHERE p.material IS NOT NULL AND p.material <> '' AND $where GROUP BY p.material ORDER BY p.material", $params),
        'types' => db_all("SELECT p.wallet_type AS value, COUNT(*) n FROM products p WHERE p.wallet_type IS NOT NULL AND p.wallet_type <> '' AND $where GROUP BY p.wallet_type ORDER BY p.wallet_type", $params),
        'price' => db_one('SELECT MIN(' . sql_effective_price() . ') min_p, MAX(' . sql_effective_price() . ") max_p FROM products p WHERE $where", $params),
    ];
}

function collection_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM collections WHERE slug = ? AND is_active = 1', [$slug]);
}

/** Search suggestions for the header autocomplete. */
function search_suggestions(string $q, int $limit = 6): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return ['products' => [], 'categories' => []];
    }
    $res = products_query(['q' => $q, 'per_page' => $limit, 'sort' => 'relevance']);
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $cats = db_all('SELECT name, slug FROM categories WHERE is_active = 1 AND name LIKE ? ORDER BY sort_order LIMIT 4', [$like]);
    return [
        'products' => array_map(fn($p) => [
            'name' => $p['name'],
            'url' => product_url($p),
            'price' => money($p['effective_price']),
            'image' => media_url($p['image']),
        ], $res['items']),
        'categories' => array_map(fn($c) => ['name' => $c['name'], 'url' => category_url($c)], $cats),
        'total' => $res['pagination']['total'],
    ];
}

/** Track "recently viewed" in the session (most recent first). */
function remember_viewed(int $productId): void
{
    $list = array_values(array_diff($_SESSION['recently_viewed'] ?? [], [$productId]));
    array_unshift($list, $productId);
    $_SESSION['recently_viewed'] = array_slice($list, 0, 12);
}

function recently_viewed(int $excludeId = 0, int $limit = 8): array
{
    $ids = array_values(array_diff($_SESSION['recently_viewed'] ?? [], [$excludeId]));
    return products_by_ids(array_slice($ids, 0, $limit));
}
