<?php
/** Catalogue queries: categories, products, variants, filters, badges. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

const PRICE_SQL = '(CASE WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.regular_price THEN p.sale_price ELSE p.regular_price END)';
const COMPLETED_ORDER_SQL = "(o.status = 'delivered' OR (o.payment_status = 'paid' AND o.status NOT IN ('cancelled','returned')))";

// ---------------------------------------------------------------------
// Categories
// ---------------------------------------------------------------------
function categories_all(bool $activeOnly = true): array
{
    static $cache = [];
    $k = (int)$activeOnly;
    if (!isset($cache[$k])) {
        $sql = 'SELECT * FROM categories' . ($activeOnly ? " WHERE status = 'active'" : '') . ' ORDER BY sort_order, name';
        $cache[$k] = [];
        foreach (db_all($sql) as $c) $cache[$k][(int)$c['id']] = $c;
    }
    return $cache[$k];
}

/** Nested tree: each node gets a 'children' array. */
function categories_tree(bool $activeOnly = true, ?int $parent = null): array
{
    $out = [];
    foreach (categories_all($activeOnly) as $c) {
        if (($c['parent_id'] === null ? null : (int)$c['parent_id']) === $parent) {
            $c['children'] = categories_tree($activeOnly, (int)$c['id']);
            $out[] = $c;
        }
    }
    return $out;
}

/** Flat list with depth for <select> menus: [id => '— — Name']. */
function categories_options(bool $activeOnly = false, ?int $exclude = null): array
{
    $out = [];
    $walk = function (array $nodes, int $depth) use (&$walk, &$out, $exclude) {
        foreach ($nodes as $n) {
            if ($exclude !== null && (int)$n['id'] === $exclude) continue;
            $out[(int)$n['id']] = str_repeat('— ', $depth) . $n['name'];
            $walk($n['children'], $depth + 1);
        }
    };
    $walk(categories_tree($activeOnly), 0);
    return $out;
}

function category_descendant_ids(int $id, bool $activeOnly = true): array
{
    $ids = [$id];
    $all = categories_all($activeOnly);
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($all as $c) {
            if ($c['parent_id'] !== null && in_array((int)$c['parent_id'], $ids, true) && !in_array((int)$c['id'], $ids, true)) {
                $ids[] = (int)$c['id'];
                $changed = true;
            }
        }
    }
    return $ids;
}

function category_ancestors(int $id): array
{
    $all = categories_all(false);
    $trail = [];
    $guard = 0;
    while (isset($all[$id]) && $guard++ < 20) {
        array_unshift($trail, $all[$id]);
        if ($all[$id]['parent_id'] === null) break;
        $id = (int)$all[$id]['parent_id'];
    }
    return $trail;
}

function category_url(array $c): string
{
    return url('category/' . $c['slug']);
}

// ---------------------------------------------------------------------
// Products
// ---------------------------------------------------------------------
function product_url(array $p): string
{
    return url('product/' . $p['slug']);
}

function product_price(array $p): float
{
    $r = (float)$p['regular_price'];
    $s = $p['sale_price'] !== null ? (float)$p['sale_price'] : 0;
    return ($s > 0 && $s < $r) ? $s : $r;
}

function product_on_sale(array $p): bool
{
    return $p['sale_price'] !== null && (float)$p['sale_price'] > 0 && (float)$p['sale_price'] < (float)$p['regular_price'];
}

/** Price for a specific variant (variant overrides win over product prices). */
function variant_prices(array $product, array $variant): array
{
    $regular = $variant['price_override'] !== null ? (float)$variant['price_override'] : (float)$product['regular_price'];
    $sale = $variant['sale_price_override'] !== null ? (float)$variant['sale_price_override']
        : ($product['sale_price'] !== null ? (float)$product['sale_price'] : 0);
    $price = ($sale > 0 && $sale < $regular) ? $sale : $regular;
    return ['regular' => $regular, 'price' => $price, 'on_sale' => $price < $regular];
}

/**
 * Product listing with filters. Options:
 *  category_ids[], collection_id, ids[], exclude_ids[], flag (new_arrival|best_seller|featured|handcrafted|limited),
 *  q, price_min, price_max, values[attribute_code => [value_id,...]], in_stock (bool),
 *  sort (newest|price_asc|price_desc|best_selling|name|featured|manual), limit, offset
 */
function products_query(array $o): array
{
    $where = ["p.status = 'published'"];
    $params = [];
    $joins = '';

    if (!empty($o['category_ids'])) {
        $ids = array_map('intval', $o['category_ids']);
        $in = db_in($ids);
        $where[] = "(p.category_id IN ($in) OR p.subcategory_id IN ($in) OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id IN ($in)))";
        array_push($params, ...$ids, ...$ids, ...$ids);
    }
    if (!empty($o['collection_id'])) {
        $joins .= ' JOIN collection_products cp ON cp.product_id = p.id AND cp.collection_id = ?';
        array_unshift($params, (int)$o['collection_id']);
    }
    if (isset($o['ids'])) {
        $ids = array_map('intval', $o['ids']) ?: [0];
        $where[] = 'p.id IN (' . db_in($ids) . ')';
        array_push($params, ...$ids);
    }
    if (!empty($o['exclude_ids'])) {
        $ids = array_map('intval', $o['exclude_ids']);
        $where[] = 'p.id NOT IN (' . db_in($ids) . ')';
        array_push($params, ...$ids);
    }
    $flags = ['new_arrival' => 'is_new_arrival', 'best_seller' => 'is_best_seller', 'featured' => 'is_featured', 'handcrafted' => 'is_handcrafted', 'limited' => 'is_limited', 'popular' => 'is_popular'];
    if (!empty($o['flag']) && isset($flags[$o['flag']])) {
        $where[] = 'p.' . $flags[$o['flag']] . ' = 1';
    }
    if (!empty($o['q'])) {
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], $o['q']) . '%';
        $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.sku LIKE ? OR p.embroidery_type LIKE ? OR p.fabric LIKE ?)';
        array_push($params, $q, $q, $q, $q, $q);
    }
    if (isset($o['price_min']) && $o['price_min'] !== '' && is_numeric($o['price_min'])) {
        $where[] = PRICE_SQL . ' >= ?';
        $params[] = (float)$o['price_min'];
    }
    if (isset($o['price_max']) && $o['price_max'] !== '' && is_numeric($o['price_max'])) {
        $where[] = PRICE_SQL . ' <= ?';
        $params[] = (float)$o['price_max'];
    }
    if (!empty($o['values'])) {
        $variantAttrs = db_col('SELECT code FROM attributes WHERE is_variant = 1');
        foreach ($o['values'] as $code => $vals) {
            $vals = array_values(array_filter(array_map('intval', (array)$vals)));
            if (!$vals) continue;
            $in = db_in($vals);
            if (in_array($code, $variantAttrs, true)) {
                $where[] = "EXISTS (SELECT 1 FROM product_variants v JOIN product_variant_values pvv ON pvv.variant_id = v.id
                            WHERE v.product_id = p.id AND v.status = 'active' AND pvv.attribute_value_id IN ($in))";
            } else {
                $where[] = "EXISTS (SELECT 1 FROM product_attribute_values pav WHERE pav.product_id = p.id AND pav.attribute_value_id IN ($in))";
            }
            array_push($params, ...$vals);
        }
    }
    if (!empty($o['in_stock'])) {
        $where[] = "(p.track_inventory = 0 OR EXISTS (SELECT 1 FROM product_variants v JOIN product_inventory i ON i.variant_id = v.id
                    WHERE v.product_id = p.id AND v.status = 'active' AND i.quantity > 0))";
    }
    if (!empty($o['fulfillment'])) {
        $where[] = 'p.fulfillment_type = ?';
        $params[] = $o['fulfillment'] === 'made_to_order' ? 'made_to_order' : 'ready_to_ship';
    }

    $sorts = [
        'newest' => 'COALESCE(p.published_at, p.created_at) DESC, p.id DESC',
        'price_asc' => PRICE_SQL . ' ASC, p.id DESC',
        'price_desc' => PRICE_SQL . ' DESC, p.id DESC',
        'name' => 'p.name ASC',
        'featured' => 'p.is_featured DESC, p.is_best_seller DESC, COALESCE(p.published_at, p.created_at) DESC',
        'best_selling' => 'units_sold DESC, p.is_best_seller DESC, p.id DESC',
        'rating' => 'p.rating_avg DESC, p.rating_count DESC',
    ];
    $sort = $o['sort'] ?? 'featured';
    if ($sort === 'manual' && !empty($o['collection_id'])) {
        $order = 'cp.sort_order, p.id DESC';
    } elseif ($sort === 'manual' && isset($o['ids'])) {
        $ids = array_map('intval', $o['ids']) ?: [0];
        $order = 'FIELD(p.id,' . implode(',', $ids) . ')';
    } else {
        $order = $sorts[$sort] ?? $sorts['featured'];
    }

    $soldSql = '';
    if ($sort === 'best_selling') {
        $soldSql = ', (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id
                      WHERE oi.product_id = p.id AND ' . COMPLETED_ORDER_SQL . ') AS units_sold';
    }

    $whereSql = implode(' AND ', $where);
    $total = (int)db_val("SELECT COUNT(*) FROM products p $joins WHERE $whereSql", $params);
    $limit = clamp_int($o['limit'] ?? 24, 1, 200);
    $offset = max(0, (int)($o['offset'] ?? 0));
    $rows = db_all("SELECT p.* $soldSql FROM products p $joins WHERE $whereSql ORDER BY $order LIMIT $limit OFFSET $offset", $params);
    return [products_hydrate($rows), $total];
}

/** Attach images, colour swatches, stock and badges to product rows (batched). */
function products_hydrate(array $rows): array
{
    if (!$rows) return [];
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $in = db_in($ids);

    $images = [];
    foreach (db_all("SELECT product_id, path, alt_text FROM product_images WHERE product_id IN ($in) ORDER BY is_main DESC, sort_order, id", $ids) as $img) {
        $images[(int)$img['product_id']][] = $img;
    }
    $colors = [];
    foreach (db_all("SELECT DISTINCT v.product_id, av.id, av.value, av.swatch_hex, av.sort_order
                     FROM product_variants v
                     JOIN product_variant_values pvv ON pvv.variant_id = v.id
                     JOIN attribute_values av ON av.id = pvv.attribute_value_id
                     JOIN attributes a ON a.id = av.attribute_id AND a.code = 'color'
                     WHERE v.product_id IN ($in) AND v.status = 'active' ORDER BY av.sort_order", $ids) as $c) {
        $colors[(int)$c['product_id']][] = $c;
    }
    $stock = [];
    foreach (db_all("SELECT v.product_id, SUM(GREATEST(i.quantity,0)) AS qty FROM product_variants v
                     LEFT JOIN product_inventory i ON i.variant_id = v.id
                     WHERE v.product_id IN ($in) AND v.status = 'active' GROUP BY v.product_id", $ids) as $s) {
        $stock[(int)$s['product_id']] = (int)$s['qty'];
    }
    $variantCounts = [];
    foreach (db_all("SELECT product_id, COUNT(*) n, MIN(id) first_id FROM product_variants WHERE product_id IN ($in) AND status = 'active' GROUP BY product_id", $ids) as $v) {
        $variantCounts[(int)$v['product_id']] = $v;
    }

    foreach ($rows as &$r) {
        $id = (int)$r['id'];
        $imgs = $images[$id] ?? [];
        $r['image'] = $imgs[0]['path'] ?? null;
        $r['image_alt'] = $imgs[0]['alt_text'] ?? $r['name'];
        $r['hover_image'] = $imgs[1]['path'] ?? null;
        $r['colors'] = $colors[$id] ?? [];
        $r['stock_qty'] = $stock[$id] ?? 0;
        $r['in_stock'] = !$r['track_inventory'] || $r['stock_qty'] > 0;
        $r['low_stock'] = $r['track_inventory'] && $r['stock_qty'] > 0 && $r['stock_qty'] <= (int)$r['low_stock_threshold'];
        $r['price'] = product_price($r);
        $r['on_sale'] = product_on_sale($r);
        $r['single_variant_id'] = (($variantCounts[$id]['n'] ?? 0) == 1) ? (int)$variantCounts[$id]['first_id'] : null;
        $r['badges'] = product_badges($r);
    }
    return $rows;
}

function product_badges(array $p): array
{
    $b = [];
    if (!empty($p['is_best_seller'])) $b[] = ['Best Seller', 'gold'];
    if (!empty($p['is_limited'])) $b[] = ['Limited Edition', 'dark'];
    if (!empty($p['is_new_arrival'])) $b[] = ['New Arrival', 'olive'];
    if (!empty($p['is_popular'])) $b[] = ['Popular Design', 'sage'];
    if (isset($p['in_stock']) && !$p['in_stock']) $b = [['Sold Out', 'muted']];
    elseif (!empty($p['on_sale'])) $b[] = ['Sale', 'rose'];
    return array_slice($b, 0, 2);
}

function product_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $p = db_one('SELECT * FROM products WHERE slug = ?' . ($publishedOnly ? " AND status = 'published'" : ''), [$slug]);
    return $p;
}

function product_images(int $productId): array
{
    return db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id', [$productId]);
}

/**
 * Variants with their option values and stock, plus the option matrix used
 * by the product page: ['options' => [code => [name, values[id => value]]], 'variants' => [...]].
 */
function product_variant_matrix(array $product): array
{
    $variants = db_all("SELECT v.*, COALESCE(i.quantity, 0) AS stock FROM product_variants v
                        LEFT JOIN product_inventory i ON i.variant_id = v.id
                        WHERE v.product_id = ? AND v.status = 'active' ORDER BY v.sort_order, v.id", [(int)$product['id']]);
    if (!$variants) return ['options' => [], 'variants' => []];
    $vids = array_map(fn($v) => (int)$v['id'], $variants);
    $vals = db_all('SELECT pvv.variant_id, av.id, av.value, av.swatch_hex, av.sort_order, a.code, a.name AS attr_name, a.sort_order AS attr_sort
                    FROM product_variant_values pvv JOIN attribute_values av ON av.id = pvv.attribute_value_id
                    JOIN attributes a ON a.id = av.attribute_id WHERE pvv.variant_id IN (' . db_in($vids) . ')
                    ORDER BY a.sort_order, av.sort_order', $vids);
    $options = [];
    $byVariant = [];
    foreach ($vals as $v) {
        $options[$v['code']]['name'] = $v['attr_name'];
        $options[$v['code']]['values'][(int)$v['id']] = ['value' => $v['value'], 'hex' => $v['swatch_hex'], 'sort' => (int)$v['sort_order']];
        $byVariant[(int)$v['variant_id']][$v['code']] = (int)$v['id'];
    }
    foreach ($options as &$opt) {
        uasort($opt['values'], fn($a, $b) => $a['sort'] <=> $b['sort']);
    }
    unset($opt);
    $out = [];
    foreach ($variants as $v) {
        $prices = variant_prices($product, $v);
        $available = !$product['track_inventory'] || (int)$v['stock'] > 0;
        $out[] = [
            'id' => (int)$v['id'],
            'sku' => $v['sku'],
            'values' => $byVariant[(int)$v['id']] ?? [],
            'price' => $prices['price'],
            'regular' => $prices['regular'],
            'stock' => $product['track_inventory'] ? (int)$v['stock'] : null,
            'available' => $available,
        ];
    }
    return ['options' => $options, 'variants' => $out];
}

function variant_label(int $variantId): string
{
    $vals = db_all('SELECT a.name, av.value FROM product_variant_values pvv JOIN attribute_values av ON av.id = pvv.attribute_value_id
                    JOIN attributes a ON a.id = av.attribute_id WHERE pvv.variant_id = ? ORDER BY a.sort_order', [$variantId]);
    return implode(' / ', array_map(fn($v) => $v['name'] . ': ' . $v['value'], $vals));
}

/** Descriptive facets (sleeve, type, craft, occasion) for a product. */
function product_facets(int $productId): array
{
    $rows = db_all('SELECT a.code, a.name, av.value FROM product_attribute_values pav JOIN attribute_values av ON av.id = pav.attribute_value_id
                    JOIN attributes a ON a.id = av.attribute_id WHERE pav.product_id = ? ORDER BY a.sort_order, av.sort_order', [$productId]);
    $out = [];
    foreach ($rows as $r) $out[$r['name']][] = $r['value'];
    return $out;
}

function product_relations(int $productId, string $type, int $limit = 8): array
{
    $ids = db_col('SELECT related_id FROM product_relations WHERE product_id = ? AND relation_type = ? ORDER BY sort_order', [$productId, $type]);
    if (!$ids) return [];
    [$rows] = products_query(['ids' => $ids, 'sort' => 'manual', 'limit' => $limit]);
    return $rows;
}

/** Related products: manual first, then same category. */
function product_related(array $p, int $limit = 8): array
{
    $rows = product_relations((int)$p['id'], 'related', $limit);
    if (count($rows) < $limit && $p['category_id']) {
        $exclude = array_merge([(int)$p['id']], array_map(fn($r) => (int)$r['id'], $rows));
        [$more] = products_query(['category_ids' => category_descendant_ids((int)$p['category_id']), 'exclude_ids' => $exclude, 'limit' => $limit - count($rows), 'sort' => 'featured']);
        $rows = array_merge($rows, $more);
    }
    return $rows;
}

/** Automatic best-sellers ranking by units sold in completed orders. */
function best_seller_ids(int $limit = 12, int $days = 0): array
{
    $sql = 'SELECT oi.product_id, SUM(oi.quantity) units FROM order_items oi JOIN orders o ON o.id = oi.order_id
            JOIN products p ON p.id = oi.product_id AND p.status = \'published\'
            WHERE ' . COMPLETED_ORDER_SQL . ($days > 0 ? ' AND o.created_at >= DATE_SUB(NOW(), INTERVAL ' . (int)$days . ' DAY)' : '') . '
            GROUP BY oi.product_id ORDER BY units DESC LIMIT ' . (int)$limit;
    return array_map('intval', db_col($sql));
}

/** Filter facets for listing pages: attributes with values that exist among published products. */
function filter_facets(): array
{
    $rows = db_all("SELECT a.code, a.name, a.is_variant, av.id, av.value, av.swatch_hex FROM attributes a
                    JOIN attribute_values av ON av.attribute_id = a.id
                    WHERE a.is_filterable = 1 AND (
                      (a.is_variant = 1 AND EXISTS (SELECT 1 FROM product_variant_values pvv JOIN product_variants v ON v.id = pvv.variant_id
                         JOIN products p ON p.id = v.product_id AND p.status = 'published' WHERE pvv.attribute_value_id = av.id))
                      OR (a.is_variant = 0 AND EXISTS (SELECT 1 FROM product_attribute_values pav JOIN products p ON p.id = pav.product_id AND p.status = 'published'
                         WHERE pav.attribute_value_id = av.id)))
                    ORDER BY a.sort_order, av.sort_order");
    $out = [];
    foreach ($rows as $r) {
        $out[$r['code']]['name'] = $r['name'];
        $out[$r['code']]['values'][] = $r;
    }
    return $out;
}

/** Parse filter parameters from the query string (?size[]=1&color[]=4&price_min=...). */
function listing_filters_from_request(): array
{
    $values = [];
    foreach (db_col('SELECT code FROM attributes WHERE is_filterable = 1') as $code) {
        if (!empty($_GET[$code])) {
            $values[$code] = array_slice(array_map('intval', (array)$_GET[$code]), 0, 30);
        }
    }
    return [
        'values' => $values,
        'price_min' => is_numeric(get('price_min')) ? (float)get('price_min') : '',
        'price_max' => is_numeric(get('price_max')) ? (float)get('price_max') : '',
        'in_stock' => get('in_stock') === '1',
        'fulfillment' => in_list(get('fulfillment'), ['ready_to_ship', 'made_to_order'], ''),
        'sort' => in_list(get('sort'), ['featured', 'newest', 'price_asc', 'price_desc', 'best_selling', 'name', 'rating'], 'featured'),
        'q' => mb_substr(get('q'), 0, 100),
    ];
}

function collection_url(array $c): string
{
    return !empty($c['link_url']) ? url($c['link_url']) : url('collections/' . $c['slug']);
}

function product_rating_refresh(int $productId): void
{
    db_exec("UPDATE products SET rating_avg = COALESCE((SELECT AVG(rating) FROM reviews WHERE product_id = ? AND status = 'approved'), 0),
             rating_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = 'approved') WHERE id = ?", [$productId, $productId, $productId]);
}

// ---------------------------------------------------------------------
// Wishlist (DB for customers, session for guests; merged at login)
// ---------------------------------------------------------------------
function wishlist_ids(): array
{
    $cid = customer_id();
    if (!$cid) return array_map('intval', $_SESSION['wishlist'] ?? []);
    static $ids = null;
    if ($ids === null) {
        $ids = array_map('intval', db_col('SELECT wi.product_id FROM wishlist_items wi JOIN wishlists w ON w.id = wi.wishlist_id WHERE w.customer_id = ?', [$cid]));
    }
    return $ids;
}

function wishlist_id_for(int $customerId): int
{
    $id = db_val('SELECT id FROM wishlists WHERE customer_id = ?', [$customerId]);
    return $id ? (int)$id : db_insert('INSERT INTO wishlists (customer_id) VALUES (?)', [$customerId]);
}

/** Toggle; returns true if now in wishlist. */
function wishlist_toggle(int $productId): bool
{
    $cid = customer_id();
    if (!$cid) {
        $list = array_map('intval', $_SESSION['wishlist'] ?? []);
        if (in_array($productId, $list, true)) {
            $_SESSION['wishlist'] = array_values(array_diff($list, [$productId]));
            return false;
        }
        $list[] = $productId;
        $_SESSION['wishlist'] = array_slice(array_unique($list), -100);
        return true;
    }
    $wid = wishlist_id_for($cid);
    if (db_exec('DELETE FROM wishlist_items WHERE wishlist_id = ? AND product_id = ?', [$wid, $productId])) return false;
    db_exec('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)', [$wid, $productId]);
    return true;
}

function wishlist_merge_on_login(array $guestIds, int $customerId): void
{
    if (!$guestIds) return;
    $wid = wishlist_id_for($customerId);
    foreach (array_unique(array_map('intval', $guestIds)) as $pid) {
        if (db_val("SELECT id FROM products WHERE id = ? AND status = 'published'", [$pid])) {
            db_exec('INSERT IGNORE INTO wishlist_items (wishlist_id, product_id) VALUES (?, ?)', [$wid, $pid]);
        }
    }
    unset($_SESSION['wishlist']);
}

// ---------------------------------------------------------------------
// Recently viewed (cookie of product ids)
// ---------------------------------------------------------------------
function recently_viewed_ids(): array
{
    $raw = $_COOKIE['eb_recent'] ?? '';
    return array_values(array_filter(array_map('intval', explode('.', preg_replace('/[^0-9.]/', '', $raw)))));
}

function recently_viewed_push(int $id): void
{
    $ids = array_values(array_diff(recently_viewed_ids(), [$id]));
    array_unshift($ids, $id);
    $ids = array_slice($ids, 0, 12);
    setcookie('eb_recent', implode('.', $ids), [
        'expires' => time() + 60 * 60 * 24 * 60, 'path' => (BASE_PATH ?: '') . '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}
