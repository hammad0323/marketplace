<?php
/**
 * Catalog: category tree, product queries, pricing and stock helpers.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

/* ==================================================================
 * CATEGORIES
 * ================================================================== */

/** All (non-deleted) categories keyed by id, ordered. Cached per request. */
function categories_all(bool $refresh = false): array
{
    static $cats = null;
    if ($cats === null || $refresh) {
        $cats = [];
        foreach (db_all('SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY sort_order, name') as $c) {
            $cats[(int) $c['id']] = $c;
        }
    }
    return $cats;
}

function category_get(int $id): ?array
{
    return categories_all()[$id] ?? null;
}

function category_by_slug(string $slug): ?array
{
    foreach (categories_all() as $c) {
        if ($c['slug'] === $slug) return $c;
    }
    return null;
}

/** Direct children. */
function category_children(?int $parentId, bool $activeOnly = true, bool $menuOnly = false): array
{
    $out = [];
    foreach (categories_all() as $c) {
        if ((int) $c['parent_id'] === (int) $parentId && ($parentId !== null || $c['parent_id'] === null)) {
            if ($activeOnly && !$c['is_active']) continue;
            if ($menuOnly && !$c['show_in_menu']) continue;
            $out[] = $c;
        }
    }
    return $out;
}

/** Ids of the category and every descendant (active only by default). */
function category_descendant_ids(int $id, bool $activeOnly = true): array
{
    $ids = [$id];
    foreach (category_children($id, $activeOnly) as $ch) {
        $ids = array_merge($ids, category_descendant_ids((int) $ch['id'], $activeOnly));
    }
    return $ids;
}

/** Ancestor chain root -> ... -> self. */
function category_path(int $id): array
{
    $path = [];
    $guard = 0;
    while ($id && ($c = category_get($id)) && $guard++ < 20) {
        array_unshift($path, $c);
        $id = (int) $c['parent_id'];
    }
    return $path;
}

function category_url($cat): string
{
    $id = is_array($cat) ? (int) $cat['id'] : (int) $cat;
    $slugs = array_map(function ($c) { return $c['slug']; }, category_path($id));
    return url('category/' . implode('/', $slugs) . '/');
}

/** Whole path is active (a disabled parent hides children). */
function category_is_visible(int $id): bool
{
    foreach (category_path($id) as $c) {
        if (!$c['is_active']) return false;
    }
    return (bool) category_path($id);
}

/** Nested tree for menus. */
function category_tree(?int $parent = null, int $depth = 0, int $maxDepth = 3): array
{
    if ($depth >= $maxDepth) return [];
    $out = [];
    foreach (category_children($parent, true, true) as $c) {
        $c['children'] = category_tree((int) $c['id'], $depth + 1, $maxDepth);
        $out[] = $c;
    }
    return $out;
}

/** Count of active products in a category including its descendants. */
function category_product_count(int $id): int
{
    static $cache = [];
    if (!isset($cache[$id])) {
        $ids = category_descendant_ids($id);
        $cache[$id] = (int) db_val('SELECT COUNT(DISTINCT pc.product_id) FROM product_categories pc JOIN products p ON p.id = pc.product_id
            WHERE pc.category_id IN (' . in_placeholders($ids) . ") AND p.status = 'active' AND p.deleted_at IS NULL", $ids);
    }
    return $cache[$id];
}

/** Flat list with indentation, for admin <select>s. */
function category_options(?int $parent = null, int $depth = 0, bool $activeOnly = false): array
{
    $out = [];
    foreach (category_children($parent, $activeOnly) as $c) {
        $c['depth'] = $depth;
        $c['label'] = str_repeat('— ', $depth) . $c['name'];
        $out[] = $c;
        $out = array_merge($out, category_options((int) $c['id'], $depth + 1, $activeOnly));
    }
    return $out;
}

function category_image_url(array $c): string
{
    return upload_url($c['image'] ?? null, 'assets/images/placeholder.svg');
}

/* ==================================================================
 * PRICING
 * ================================================================== */
function effective_price($price, $sale): float
{
    $price = (float) $price;
    $sale = (float) $sale;
    return ($sale > 0 && $sale < $price) ? $sale : $price;
}

function discount_percent($price, $sale): int
{
    $price = (float) $price;
    $sale = (float) $sale;
    return ($sale > 0 && $sale < $price && $price > 0) ? (int) round(100 - ($sale / $price * 100)) : 0;
}

/** SQL expression for effective price on alias p. */
function sql_effective_price(string $a = 'p'): string
{
    return "(CASE WHEN $a.sale_price > 0 AND $a.sale_price < $a.price THEN $a.sale_price ELSE $a.price END)";
}

/* ==================================================================
 * PRODUCTS
 * ================================================================== */

/** Common SELECT for product cards & listings. */
function product_select_sql(): string
{
    return "SELECT p.id, p.name, p.name_urdu, p.slug, p.sku, p.barcode, p.isbn, p.price, p.sale_price, p.cost_price, p.unit, p.unit_value,
        p.status, p.is_featured, p.is_bestseller, p.is_rashan_item, p.has_variations, p.track_stock, p.low_stock_threshold,
        p.min_order_qty, p.max_order_qty, p.primary_category_id, p.brand_id, p.sales_count, p.rating_avg, p.rating_count, p.created_at,
        p.short_description,
        b.name AS brand_name, b.slug AS brand_slug,
        (SELECT pi.thumb FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS thumb,
        (SELECT pi.image FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image,
        (SELECT pi.alt_text FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_alt,
        COALESCE((SELECT SUM(i.quantity) FROM inventory i WHERE i.product_id = p.id
            AND ((p.has_variations = 0 AND i.variation_id = 0) OR (p.has_variations = 1 AND i.variation_id > 0))), 0) AS stock,
        (SELECT MIN(CASE WHEN v.sale_price > 0 AND v.sale_price < v.price THEN v.sale_price ELSE v.price END)
            FROM product_variations v WHERE v.product_id = p.id AND v.is_active = 1) AS var_min_price,
        " . sql_effective_price() . " AS eff_price
        FROM products p LEFT JOIN brands b ON b.id = p.brand_id";
}

/**
 * Flexible product query used by listings, homepage sections, search.
 * Options: category_id, brand_ids[], ids[], exclude[], min_price, max_price, in_stock,
 *          featured, bestseller, on_sale, rashan, sort, limit, offset, with_total, search_ids[]
 */
function products_query(array $o = []): array
{
    $where = ["p.status = 'active'", 'p.deleted_at IS NULL'];
    $params = [];
    if (!empty($o['category_id'])) {
        $ids = category_descendant_ids((int) $o['category_id']);
        $where[] = 'p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))';
        $params = array_merge($params, $ids);
    }
    if (!empty($o['category_ids'])) {
        $ids = [];
        foreach ($o['category_ids'] as $cid) $ids = array_merge($ids, category_descendant_ids((int) $cid));
        $where[] = 'p.id IN (SELECT product_id FROM product_categories WHERE category_id IN (' . in_placeholders($ids) . '))';
        $params = array_merge($params, $ids);
    }
    if (!empty($o['brand_ids'])) {
        $b = array_map('intval', $o['brand_ids']);
        $where[] = 'p.brand_id IN (' . in_placeholders($b) . ')';
        $params = array_merge($params, $b);
    }
    if (isset($o['ids'])) {
        $ids = array_map('intval', $o['ids']) ?: [0];
        $where[] = 'p.id IN (' . in_placeholders($ids) . ')';
        $params = array_merge($params, $ids);
    }
    if (!empty($o['exclude'])) {
        $ex = array_map('intval', $o['exclude']);
        $where[] = 'p.id NOT IN (' . in_placeholders($ex) . ')';
        $params = array_merge($params, $ex);
    }
    if (isset($o['min_price']) && $o['min_price'] !== null && $o['min_price'] !== '') {
        $where[] = sql_effective_price() . ' >= ?';
        $params[] = (float) $o['min_price'];
    }
    if (isset($o['max_price']) && $o['max_price'] !== null && $o['max_price'] !== '') {
        $where[] = sql_effective_price() . ' <= ?';
        $params[] = (float) $o['max_price'];
    }
    if (!empty($o['in_stock'])) {
        $where[] = '(p.track_stock = 0 OR EXISTS (SELECT 1 FROM inventory i WHERE i.product_id = p.id AND i.quantity > 0))';
    }
    if (!empty($o['featured'])) $where[] = 'p.is_featured = 1';
    if (!empty($o['bestseller_flag'])) $where[] = 'p.is_bestseller = 1';
    if (!empty($o['on_sale'])) $where[] = '(p.sale_price > 0 AND p.sale_price < p.price)';
    if (!empty($o['rashan'])) $where[] = 'p.is_rashan_item = 1';
    if (!empty($o['created_after'])) {
        $where[] = 'p.created_at >= ?';
        $params[] = $o['created_after'];
    }

    $whereSql = ' WHERE ' . implode(' AND ', $where);
    $sort = $o['sort'] ?? 'newest';
    switch ($sort) {
        case 'price_asc':  $order = 'eff_price ASC, p.id DESC'; break;
        case 'price_desc': $order = 'eff_price DESC, p.id DESC'; break;
        case 'name':       $order = 'p.name ASC'; break;
        case 'popular':    $order = 'p.is_bestseller DESC, p.sales_count DESC, p.views_count DESC, p.id DESC'; break;
        case 'bestselling': $order = 'p.sales_count DESC, p.is_bestseller DESC, p.id DESC'; break;
        case 'trending':   $order = 'p.views_count DESC, p.sales_count DESC, p.id DESC'; break;
        case 'rating':     $order = 'p.rating_avg DESC, p.rating_count DESC'; break;
        case 'discount':   $order = '(p.price - ' . sql_effective_price() . ') / GREATEST(p.price,1) DESC'; break;
        case 'random':     $order = 'RAND()'; break;
        case 'ids':        // keep the order of $o['ids'] (search relevance / manual selection)
            $ids = array_map('intval', $o['ids'] ?? []) ?: [0];
            $order = 'FIELD(p.id, ' . implode(',', $ids) . ')';
            break;
        default:           $order = 'p.created_at DESC, p.id DESC';
    }
    $limit = max(1, min(200, (int) ($o['limit'] ?? 24)));
    $offset = max(0, (int) ($o['offset'] ?? 0));

    $items = db_all(product_select_sql() . $whereSql . " ORDER BY $order LIMIT $limit OFFSET $offset", $params);
    $total = null;
    if (!empty($o['with_total'])) {
        $total = (int) db_val('SELECT COUNT(*) FROM products p' . $whereSql, $params);
    }
    return ['items' => $items, 'total' => $total];
}

function product_url(array $p): string
{
    return url('product/' . $p['slug'] . '/');
}

function product_get_by_slug(string $slug): ?array
{
    return db_row(product_select_sql() . " WHERE p.slug = ? AND p.deleted_at IS NULL", [$slug]);
}

function product_get(int $id): ?array
{
    return db_row(product_select_sql() . ' WHERE p.id = ? AND p.deleted_at IS NULL', [$id]);
}

function product_full(int $id): ?array
{
    return db_row('SELECT * FROM products WHERE id = ? AND deleted_at IS NULL', [$id]);
}

function product_images(int $pid): array
{
    return db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id', [$pid]);
}

function product_variations(int $pid, bool $activeOnly = true): array
{
    return db_all('SELECT v.*, COALESCE(i.quantity, 0) AS stock FROM product_variations v
        LEFT JOIN inventory i ON i.product_id = v.product_id AND i.variation_id = v.id
        WHERE v.product_id = ?' . ($activeOnly ? ' AND v.is_active = 1' : '') . ' ORDER BY v.sort_order, v.id', [$pid]);
}

function product_category_ids(int $pid): array
{
    return array_map('intval', db_col('SELECT category_id FROM product_categories WHERE product_id = ?', [$pid]));
}

/** Display price for cards (variation products show the lowest price). */
function product_display_price(array $p): array
{
    if (!empty($p['has_variations']) && $p['var_min_price'] !== null) {
        return ['price' => (float) $p['var_min_price'], 'old' => 0, 'from' => true, 'discount' => 0];
    }
    $eff = effective_price($p['price'], $p['sale_price']);
    $old = $eff < (float) $p['price'] ? (float) $p['price'] : 0;
    return ['price' => $eff, 'old' => $old, 'from' => false, 'discount' => discount_percent($p['price'], $p['sale_price'])];
}

function product_in_stock(array $p): bool
{
    return empty($p['track_stock']) || (int) $p['stock'] > 0;
}

function product_stock_label(array $p): array
{
    if (empty($p['track_stock'])) return ['In stock', 'success'];
    $s = (int) $p['stock'];
    if ($s <= 0) return ['Out of stock', 'danger'];
    if ($s <= (int) $p['low_stock_threshold']) return ['Only ' . $s . ' left', 'warning'];
    return ['In stock', 'success'];
}

function product_thumb_url(array $p): string
{
    return upload_url($p['thumb'] ?? ($p['image'] ?? null), 'assets/images/placeholder.svg');
}

function product_image_url(array $p): string
{
    return upload_url($p['image'] ?? ($p['thumb'] ?? null), 'assets/images/placeholder.svg');
}

/**
 * Resolve a sellable item (product + optional variation) with live price & stock.
 * Server-side source of truth used by cart, checkout, rashan builder and POS.
 */
function sellable_item(int $productId, int $variationId = 0, bool $allowInactive = false): ?array
{
    $p = db_row('SELECT p.*, b.name AS brand_name FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id = ? AND p.deleted_at IS NULL', [$productId]);
    if (!$p || (!$allowInactive && $p['status'] !== 'active')) {
        return null;
    }
    $item = [
        'product_id' => (int) $p['id'],
        'variation_id' => 0,
        'name' => $p['name'],
        'name_urdu' => $p['name_urdu'],
        'variation_name' => null,
        'slug' => $p['slug'],
        'sku' => $p['sku'],
        'unit' => $p['unit'],
        'price' => (float) $p['price'],
        'sale_price' => $p['sale_price'] !== null ? (float) $p['sale_price'] : null,
        'cost_price' => (float) $p['cost_price'],
        'track_stock' => (int) $p['track_stock'],
        'min_qty' => max(1, (int) $p['min_order_qty']),
        'max_qty' => $p['max_order_qty'] ? (int) $p['max_order_qty'] : null,
        'primary_category_id' => (int) $p['primary_category_id'],
    ];
    if ((int) $p['has_variations']) {
        if (!$variationId) {
            return null; // must pick a variation
        }
        $v = db_row('SELECT * FROM product_variations WHERE id = ? AND product_id = ?' . ($allowInactive ? '' : ' AND is_active = 1'), [$variationId, $productId]);
        if (!$v) return null;
        $item['variation_id'] = (int) $v['id'];
        $item['variation_name'] = $v['name'];
        $item['sku'] = $v['sku'];
        $item['price'] = (float) $v['price'];
        $item['sale_price'] = $v['sale_price'] !== null ? (float) $v['sale_price'] : null;
        $item['cost_price'] = (float) $v['cost_price'];
    } elseif ($variationId) {
        return null;
    }
    $item['unit_price'] = effective_price($item['price'], $item['sale_price']);
    $item['stock'] = stock_get($item['product_id'], $item['variation_id']);
    $img = db_row('SELECT thumb, image FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id LIMIT 1', [$productId]);
    $item['thumb'] = $img['thumb'] ?? ($img['image'] ?? null);
    return $item;
}

/** Normalised search text for a product (used by the search engine). */
function product_rebuild_search(int $pid): void
{
    $p = db_row('SELECT p.*, b.name AS brand_name FROM products p LEFT JOIN brands b ON b.id = p.brand_id WHERE p.id = ?', [$pid]);
    if (!$p) return;
    $parts = [$p['name'], $p['name_urdu'], $p['sku'], $p['barcode'], $p['product_code'], $p['isbn'], $p['brand_name'], $p['keywords'], $p['unit'],
        $p['unit_value'] ? rtrim(rtrim((string) $p['unit_value'], '0'), '.') . ' ' . $p['unit'] : ''];
    foreach (product_category_ids($pid) as $cid) {
        foreach (category_path($cid) as $c) {
            $parts[] = $c['name'];
            $parts[] = $c['name_urdu'];
        }
    }
    foreach (db_col('SELECT tag FROM product_tags WHERE product_id = ?', [$pid]) as $t) $parts[] = $t;
    foreach (db_all('SELECT name, sku, barcode FROM product_variations WHERE product_id = ?', [$pid]) as $v) {
        $parts[] = $v['name'];
        $parts[] = $v['sku'];
        $parts[] = $v['barcode'];
    }
    $parts[] = str_limit((string) $p['short_description'], 300);
    $text = search_normalize(implode(' ', array_filter(array_map('strval', $parts))));
    $tokens = array_unique(explode(' ', $text));
    db_exec('UPDATE products SET search_text = ? WHERE id = ?', [' ' . implode(' ', $tokens) . ' ', $pid]);
}

/* ==================================================================
 * BRANDS
 * ================================================================== */
function brands_active(): array
{
    return db_all('SELECT * FROM brands WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order, name');
}

function brand_url(array $b): string
{
    return url('brand/' . $b['slug'] . '/');
}

/* ==================================================================
 * WISHLIST helpers
 * ================================================================== */
function wishlist_ids(): array
{
    static $ids = null;
    if ($ids === null) {
        $ids = customer_id() ? array_map('intval', db_col('SELECT wi.product_id FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.customer_id = ?', [customer_id()])) : [];
    }
    return $ids;
}

function wishlist_get_id(int $customerId): int
{
    $id = (int) db_val('SELECT id FROM wishlists WHERE customer_id = ?', [$customerId]);
    return $id ?: db_insert('INSERT INTO wishlists (customer_id) VALUES (?)', [$customerId]);
}

/* ==================================================================
 * PROMOTIONS / BANNERS
 * ================================================================== */
function sql_active_window(string $a = ''): string
{
    $a = $a ? $a . '.' : '';
    return "{$a}is_active = 1 AND ({$a}starts_at IS NULL OR {$a}starts_at <= NOW()) AND ({$a}ends_at IS NULL OR {$a}ends_at >= NOW())";
}

function promotion_url(array $p): string
{
    return $p['link_url'] ? url($p['link_url']) : url('offers/' . $p['slug'] . '/');
}

function promo_types(): array
{
    return ['grocery_deal' => 'Grocery Deal', 'school_offer' => 'School Reopening Offer', 'stationery_bundle' => 'Stationery Bundle',
        'monthly_package' => 'Monthly Grocery Package', 'seasonal' => 'Seasonal Discount', 'combo' => 'Combo Deal'];
}
