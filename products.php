<?php
require __DIR__ . '/config/config.php';

$q = clean($_GET['q'] ?? '');
$categorySlug = clean($_GET['category'] ?? '');
$type = in_array($_GET['type'] ?? '', ['product', 'service'], true) ? $_GET['type'] : '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? ($q !== '' ? 'relevance' : 'newest');

$where = ["dp.is_active = 1", "d.is_premium = 1", "d.verification_status = 'verified'"];
$params = [];
$types = '';
$boolQuery = '';

if ($q !== '') {
    // Boolean-mode full-text search: each word becomes a required prefix match
    // (name/description have a FULLTEXT index — this scales far better than a
    // LIKE '%...%' scan as the catalog grows, and still matches partial words).
    $words = preg_split('/\s+/', trim($q));
    $boolQuery = implode(' ', array_map(fn($w) => '+' . preg_replace('/[+\-<>()~*"@]/', '', $w) . '*', array_filter($words)));
    $where[] = 'MATCH(dp.name, dp.description) AGAINST (? IN BOOLEAN MODE)';
    $params[] = $boolQuery;
    $types .= 's';
}
if ($categorySlug !== '') {
    $where[] = 'pc.slug = ?';
    $params[] = $categorySlug;
    $types .= 's';
}
if ($type !== '') {
    $where[] = 'dp.type = ?';
    $params[] = $type;
    $types .= 's';
}
if ($minPrice !== '') {
    $where[] = 'dp.price >= ?';
    $params[] = (float) $minPrice;
    $types .= 'd';
}
if ($maxPrice !== '') {
    $where[] = 'dp.price <= ?';
    $params[] = (float) $maxPrice;
    $types .= 'd';
}

$whereSql = implode(' AND ', $where);
$useRelevanceOrder = $sort === 'relevance' && $q !== '';
$orderSql = match (true) {
    $useRelevanceOrder => 'MATCH(dp.name, dp.description) AGAINST (? IN BOOLEAN MODE) DESC',
    $sort === 'price_low' => 'dp.price ASC',
    $sort === 'price_high' => 'dp.price DESC',
    default => 'dp.created_at DESC',
};

$countSql = "SELECT COUNT(*) c FROM doctor_products dp JOIN doctors d ON d.id = dp.doctor_id LEFT JOIN product_categories pc ON pc.id = dp.category_id WHERE $whereSql";
$stmt = mysqli_prepare(db(), $countSql);
if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$total = (int) mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];
mysqli_stmt_close($stmt);

$pagination = paginate($total, 12);

$listSql = "SELECT dp.*, u.full_name AS doctor_name, d.slug AS doctor_slug, pc.name AS category_name
    FROM doctor_products dp
    JOIN doctors d ON d.id = dp.doctor_id
    JOIN users u ON u.id = d.user_id
    LEFT JOIN product_categories pc ON pc.id = dp.category_id
    WHERE $whereSql ORDER BY $orderSql LIMIT ? OFFSET ?";
$stmt = mysqli_prepare(db(), $listSql);
$orderParams = $useRelevanceOrder ? [$boolQuery] : [];
$allTypes = $types . ($useRelevanceOrder ? 's' : '') . 'ii';
$allParams = array_merge($params, $orderParams, [$pagination['per_page'], $pagination['offset']]);
mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
mysqli_stmt_execute($stmt);
$products = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$categories = mysqli_query(db(), 'SELECT id, name, slug FROM product_categories ORDER BY name');

$pageTitle = ($q !== '' ? 'Search: ' . $q . ' — ' : '') . 'Products & Services — ' . SITE_NAME;
$metaDescription = 'Browse health products and service packages offered directly by verified, premium doctors on ' . SITE_NAME . '.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);padding-bottom:0;">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Products &amp; Services</span></nav>
        <h1 style="font-size:32px;margin-bottom:8px;">Products &amp; Services</h1>
        <p style="color:var(--color-text-muted);margin-bottom:32px;"><?= $total ?> listing<?= $total === 1 ? '' : 's' ?> found<?= $q !== '' ? ' for "' . e($q) . '"' : '' ?></p>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container split-sidebar-left" style="gap:32px;">
        <aside class="card" style="padding:24px;position:sticky;top:calc(var(--header-height) + 20px);">
            <form method="get" id="product-filter-form">
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="e.g. blood pressure, therapy" value="<?= e($q) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="">All Types</option>
                        <option value="product" <?= $type === 'product' ? 'selected' : '' ?>>Products</option>
                        <option value="service" <?= $type === 'service' ? 'selected' : '' ?>>Services</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= e($c['slug']) ?>" <?= $categorySlug === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Price Range ($)</label>
                    <div style="display:flex;gap:8px;">
                        <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?= e($minPrice) ?>">
                        <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?= e($maxPrice) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-control">
                        <?php if ($q !== ''): ?><option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>Best Match</option><?php endif; ?>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                <a href="/products" class="btn btn-ghost btn-block" style="margin-top:8px;">Clear All</a>
            </form>
        </aside>

        <div>
            <?php if ($total === 0): ?>
            <div class="empty-state card">
                <i class="ri-search-line"></i>
                <h4>No listings match your search</h4>
                <p>Try a different keyword or clear your filters.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-3 stagger">
                <?php foreach ($products as $p): ?>
                <a href="/product-detail?slug=<?= e($p['slug']) ?>" class="card card-hover" style="overflow:hidden;display:block;" data-reveal>
                    <?php if ($p['image']): ?>
                    <img src="/uploads/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:160px;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:160px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:36px;"><i class="<?= $p['type'] === 'service' ? 'ri-heart-pulse-line' : 'ri-capsule-line' ?>"></i></div>
                    <?php endif; ?>
                    <div style="padding:18px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <span class="badge badge-<?= $p['type'] === 'service' ? 'pending' : 'verified' ?>"><?= $p['type'] === 'service' ? 'Service' : 'Product' ?></span>
                            <strong style="color:var(--color-primary);"><?= format_currency($p['price']) ?></strong>
                        </div>
                        <strong style="display:block;margin-bottom:4px;font-size:14.5px;"><?= e($p['name']) ?></strong>
                        <p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:8px;"><?= e(excerpt($p['description'] ?? '', 70)) ?></p>
                        <span style="font-size:12px;color:var(--color-text-muted);"><i class="ri-stethoscope-line"></i> <?= e($p['doctor_name']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?= pagination_links($pagination, '/products' . ($q !== '' ? '?q=' . urlencode($q) : '')) ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
