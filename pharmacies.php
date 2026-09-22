<?php
require __DIR__ . '/config/config.php';

$q = clean($_GET['q'] ?? '');
$city = clean($_GET['city'] ?? '');
$nearLat = is_numeric($_GET['lat'] ?? null) ? (float) $_GET['lat'] : null;
$nearLng = is_numeric($_GET['lng'] ?? null) ? (float) $_GET['lng'] : null;
$nearMe = $nearLat !== null && $nearLng !== null;

$where = ["p.verification_status = 'verified'", "u.status = 'active'"];
$params = [];
$types = '';
if ($nearMe) {
    $where[] = 'p.latitude IS NOT NULL AND p.longitude IS NOT NULL';
}

if ($q !== '') {
    $where[] = '(p.store_name LIKE ? OR p.bio LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like);
    $types .= 'ss';
}
if ($city !== '') {
    $where[] = 'p.city LIKE ?';
    $params[] = '%' . $city . '%';
    $types .= 's';
}

$whereSql = implode(' AND ', $where);

$countSql = "SELECT COUNT(*) c FROM pharmacies p JOIN users u ON u.id = p.user_id WHERE $whereSql";
$stmt = mysqli_prepare(db(), $countSql);
if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$total = (int) mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];
mysqli_stmt_close($stmt);

$pagination = paginate($total, 9);

$distanceSql = $nearMe ? haversine_distance_sql('p.latitude', 'p.longitude', $nearLat, $nearLng) : null;
$orderSql = $nearMe ? 'distance_km ASC' : 'p.rating_avg DESC, p.created_at DESC';
$distanceSelect = $nearMe ? ", $distanceSql AS distance_km" : '';
$listSql = "SELECT p.*, u.full_name, u.avatar,
        (SELECT COUNT(*) FROM doctor_products dp WHERE dp.pharmacy_id = p.id AND dp.seller_type = 'pharmacy' AND dp.is_active = 1) AS product_count
        $distanceSelect
    FROM pharmacies p JOIN users u ON u.id = p.user_id
    WHERE $whereSql ORDER BY $orderSql LIMIT ? OFFSET ?";
$stmt = mysqli_prepare(db(), $listSql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
mysqli_stmt_execute($stmt);
$pharmacies = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$pageTitle = 'Find Pharmacies — ' . SITE_NAME;
$metaDescription = 'Browse verified pharmacies and medicine stores. Order medicines directly from registered, verified sellers.';
$canonical = filtered_canonical('/pharmacies', ['q' => $q, 'city' => $city], $pagination['page']);
$breadcrumbs = [['name' => 'Home', 'url' => APP_URL . '/'], ['name' => 'Find Pharmacies']];
if ($q !== '') {
    $metaRobots = 'noindex, follow'; // free-text search results are thin/duplicate content
}
$extraScripts = '<script>document.addEventListener("DOMContentLoaded",function(){if(window.setupNearMeButton)setupNearMeButton("#near-me-btn","/pharmacies");});</script>';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);padding-bottom:0;">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Find Pharmacies</span></nav>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="font-size:32px;margin-bottom:8px;">Find Pharmacies</h1>
                <p style="color:var(--color-text-muted);margin-bottom:0;"><?= $total ?> verified pharmac<?= $total === 1 ? 'y' : 'ies' ?> found<?= $nearMe ? ', sorted by distance from you' : '' ?></p>
            </div>
            <?php if ($nearMe): ?>
            <a href="<?= e(filtered_canonical('/pharmacies', ['q' => $q, 'city' => $city])) ?>" class="btn btn-outline btn-sm"><i class="ri-close-line"></i> Clear "Near Me"</a>
            <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm" id="near-me-btn"><i class="ri-map-pin-user-fill"></i> Near Me</button>
            <?php endif; ?>
        </div>
        <div style="margin-bottom:32px;"></div>
    </div>
</section>

<section class="section" style="padding-top:0;">
    <div class="container split-sidebar-left" style="gap:32px;">
        <aside class="card" style="padding:24px;position:sticky;top:calc(var(--header-height) + 20px);">
            <form method="get" id="filter-form">
                <?php if ($nearMe): ?>
                <input type="hidden" name="lat" value="<?= e($nearLat) ?>">
                <input type="hidden" name="lng" value="<?= e($nearLng) ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Store name" value="<?= e($q) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" placeholder="e.g. Lahore" value="<?= e($city) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                <a href="/pharmacies" class="btn btn-ghost btn-block" style="margin-top:8px;">Clear All</a>
            </form>
        </aside>

        <div>
            <h2 class="sr-only"><?= (int) $total ?> pharmac<?= $total == 1 ? 'y' : 'ies' ?> found</h2>
            <?php if ($total === 0): ?>
            <div class="empty-state card">
                <i class="ri-capsule-line"></i>
                <p style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--color-text);">No pharmacies match your filters</p>
                <p>Try broadening your search or clearing filters.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-3 stagger">
                <?php foreach ($pharmacies as $p): ?>
                <a href="<?= e(pharmacy_url($p['slug'])) ?>" class="card card-hover" style="padding:20px;display:block;" data-reveal>
                    <img src="<?= e(avatar_url($p['avatar'], $p['store_name'])) ?>" alt="<?= e($p['store_name']) ?>" width="52" height="52" loading="lazy" style="width:52px;height:52px;border-radius:14px;object-fit:cover;margin-bottom:14px;">
                    <h3 style="font-size:16px;margin-bottom:6px;"><?= e($p['store_name']) ?></h3>
                    <p style="font-size:13px;color:var(--color-text-muted);margin-bottom:10px;"><i class="ri-map-pin-line"></i> <?= e($p['city'] ?: 'Location not set') ?><?php if (isset($p['distance_km'])): ?> · <?= number_format((float) $p['distance_km'], 1) ?> km away<?php endif; ?></p>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="badge badge-verified"><i class="ri-verified-badge-fill"></i> Verified</span>
                        <span style="font-size:12.5px;color:var(--color-text-muted);"><?= (int) $p['product_count'] ?> listing<?= $p['product_count'] == 1 ? '' : 's' ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php
            $qs = $_GET; unset($qs['page']);
            echo pagination_links($pagination, '/pharmacies' . ($qs ? '?' . http_build_query($qs) : ''));
            ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
