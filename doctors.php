<?php
require __DIR__ . '/config/config.php';

$q = clean($_GET['q'] ?? '');
$specSlug = clean($_GET['specialization'] ?? '');
$city = clean($_GET['city'] ?? '');
$minFee = $_GET['min_fee'] ?? '';
$maxFee = $_GET['max_fee'] ?? '';
$freeOnly = !empty($_GET['free']);
$premiumOnly = !empty($_GET['premium']);
$sort = $_GET['sort'] ?? 'rating';
$nearLat = is_numeric($_GET['lat'] ?? null) ? (float) $_GET['lat'] : null;
$nearLng = is_numeric($_GET['lng'] ?? null) ? (float) $_GET['lng'] : null;
$nearMe = $nearLat !== null && $nearLng !== null;

$where = ["d.verification_status = 'verified'", "u.status = 'active'"];
$params = [];
$types = '';
if ($nearMe) {
    $where[] = 'd.latitude IS NOT NULL AND d.longitude IS NOT NULL';
}

if ($q !== '') {
    $where[] = '(u.full_name LIKE ? OR d.bio LIKE ? OR d.qualification LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}
if ($specSlug !== '') {
    $where[] = 'd.id IN (SELECT ds.doctor_id FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE s.slug = ?)';
    $params[] = $specSlug;
    $types .= 's';
}
if ($city !== '') {
    $where[] = 'd.clinic_city LIKE ?';
    $params[] = '%' . $city . '%';
    $types .= 's';
}
if ($minFee !== '') {
    $where[] = 'd.consultation_fee_online >= ?';
    $params[] = (float) $minFee;
    $types .= 'd';
}
if ($maxFee !== '') {
    $where[] = 'd.consultation_fee_online <= ?';
    $params[] = (float) $maxFee;
    $types .= 'd';
}
if ($freeOnly) {
    $where[] = 'd.free_consultation = 1';
}
if ($premiumOnly) {
    $where[] = 'd.is_premium = 1';
}

$whereSql = implode(' AND ', $where);
$distanceSql = $nearMe ? haversine_distance_sql('d.latitude', 'd.longitude', $nearLat, $nearLng) : null;
$orderSql = $nearMe ? 'distance_km ASC' : match ($sort) {
    'fee_low' => 'd.consultation_fee_online ASC',
    'fee_high' => 'd.consultation_fee_online DESC',
    'experience' => 'd.experience_years DESC',
    default => 'd.is_premium DESC, d.rating_avg DESC',
};

$countSql = "SELECT COUNT(*) c FROM doctors d JOIN users u ON u.id = d.user_id WHERE $whereSql";
$stmt = mysqli_prepare(db(), $countSql);
if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$total = (int) mysqli_stmt_get_result($stmt)->fetch_assoc()['c'];
mysqli_stmt_close($stmt);

$pagination = paginate($total, 9);

$distanceSelect = $nearMe ? ", $distanceSql AS distance_km" : '';
$listSql = "SELECT d.*, u.full_name, u.avatar $distanceSelect
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE $whereSql ORDER BY $orderSql LIMIT ? OFFSET ?";
$stmt = mysqli_prepare(db(), $listSql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
mysqli_stmt_execute($stmt);
$doctors = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
foreach ($doctors as &$d) {
    $d['specializations'] = get_doctor_specializations($d['id']);
}
unset($d);

$specs = mysqli_query(db(), 'SELECT id, name, slug FROM specializations WHERE is_active = 1 ORDER BY name');
$cityOptions = mysqli_query(db(), "
    SELECT DISTINCT clinic_city FROM doctors
    WHERE verification_status = 'verified' AND clinic_city IS NOT NULL AND clinic_city != ''
    ORDER BY clinic_city ASC
");

$specName = '';
if ($specSlug !== '') {
    $stmt = mysqli_prepare(db(), 'SELECT name FROM specializations WHERE slug = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $specSlug);
    mysqli_stmt_execute($stmt);
    $specName = mysqli_stmt_get_result($stmt)->fetch_assoc()['name'] ?? '';
    mysqli_stmt_close($stmt);
}

$pageTitle = 'Find Doctors — ' . SITE_NAME;
$metaDescription = 'Search and compare verified doctors by specialty, fee, and rating. Book online or in-person consultations instantly.';
$canonical = filtered_canonical('/doctors', [
    'q' => $q, 'specialization' => $specSlug, 'city' => $city,
    'min_fee' => $minFee, 'max_fee' => $maxFee, 'free' => $freeOnly ? 1 : '', 'premium' => $premiumOnly ? 1 : '',
], $pagination['page']);
$breadcrumbs = [['name' => 'Home', 'url' => APP_URL . '/'], ['name' => 'Find Doctors']];
if ($q !== '') {
    $metaRobots = 'noindex, follow'; // free-text search results are thin/duplicate content
}
$extraScripts = '<script>document.addEventListener("DOMContentLoaded",function(){if(window.setupNearMeButton)setupNearMeButton("#near-me-btn","/doctors");});</script>';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);padding-bottom:0;">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Find Doctors</span></nav>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="font-size:32px;margin-bottom:8px;">Find Doctors</h1>
                <p style="color:var(--color-text-muted);margin-bottom:0;"><?= $total ?> verified doctor<?= $total === 1 ? '' : 's' ?> found<?= $specName ? ' in ' . e($specName) : '' ?><?= $nearMe ? ', sorted by distance from you' : '' ?></p>
            </div>
            <?php if ($nearMe): ?>
            <a href="<?= e(filtered_canonical('/doctors', ['q' => $q, 'specialization' => $specSlug, 'city' => $city, 'min_fee' => $minFee, 'max_fee' => $maxFee, 'free' => $freeOnly ? 1 : '', 'premium' => $premiumOnly ? 1 : ''])) ?>" class="btn btn-outline btn-sm"><i class="ri-close-line"></i> Clear "Near Me"</a>
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
                    <input type="text" name="q" class="form-control" placeholder="Name or keyword" value="<?= e($q) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Specialization</label>
                    <select name="specialization" class="form-control">
                        <option value="">All Specialties</option>
                        <?php while ($s = mysqli_fetch_assoc($specs)): ?>
                        <option value="<?= e($s['slug']) ?>" <?= $specSlug === $s['slug'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">City</label>
                    <select name="city" class="form-control">
                        <option value="">All Cities</option>
                        <?php while ($co = mysqli_fetch_assoc($cityOptions)): ?>
                        <option value="<?= e($co['clinic_city']) ?>" <?= $city === $co['clinic_city'] ? 'selected' : '' ?>><?= e($co['clinic_city']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Fee Range ($)</label>
                    <div style="display:flex;gap:8px;">
                        <input type="number" name="min_fee" class="form-control" placeholder="Min" value="<?= e($minFee) ?>">
                        <input type="number" name="max_fee" class="form-control" placeholder="Max" value="<?= e($maxFee) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-row"><input type="checkbox" name="free" value="1" <?= $freeOnly ? 'checked' : '' ?>> Free consultation only</label>
                </div>
                <div class="form-group">
                    <label class="checkbox-row"><input type="checkbox" name="premium" value="1" <?= $premiumOnly ? 'checked' : '' ?>> Premium doctors only</label>
                </div>
                <div class="form-group">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-control">
                        <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                        <option value="fee_low" <?= $sort === 'fee_low' ? 'selected' : '' ?>>Fee: Low to High</option>
                        <option value="fee_high" <?= $sort === 'fee_high' ? 'selected' : '' ?>>Fee: High to Low</option>
                        <option value="experience" <?= $sort === 'experience' ? 'selected' : '' ?>>Most Experienced</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                <a href="/doctors" class="btn btn-ghost btn-block" style="margin-top:8px;">Clear All</a>
            </form>
        </aside>

        <div>
            <h2 class="sr-only"><?= (int) $total ?> doctor<?= $total == 1 ? '' : 's' ?> found</h2>
            <?php if ($total === 0): ?>
            <div class="empty-state card">
                <i class="ri-user-search-line"></i>
                <p style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--color-text);">No doctors match your filters</p>
                <p>Try broadening your search or clearing filters.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-3 stagger">
                <?php foreach ($doctors as $d): require __DIR__ . '/includes/doctor-card.php'; endforeach; ?>
            </div>
            <?php
            $qs = $_GET; unset($qs['page']);
            echo pagination_links($pagination, '/doctors' . ($qs ? '?' . http_build_query($qs) : ''));
            ?>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
