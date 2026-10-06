<?php
require __DIR__ . '/config/config.php';

$slug = clean($_GET['slug'] ?? '');
if ($slug === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = mysqli_prepare(db(), 'SELECT * FROM specializations WHERE slug = ? AND is_active = 1 LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$spec = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$spec) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = mysqli_prepare(db(), "
    SELECT d.*, u.full_name, u.avatar
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
      AND d.id IN (SELECT ds.doctor_id FROM doctor_specializations ds WHERE ds.specialization_id = ?)
    ORDER BY d.is_premium DESC, d.rating_avg DESC
    LIMIT 12
");
mysqli_stmt_bind_param($stmt, 'i', $spec['id']);
mysqli_stmt_execute($stmt);
$doctors = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
foreach ($doctors as &$d) {
    $d['specializations'] = get_doctor_specializations($d['id']);
}
unset($d);

$doctorCount = mysqli_fetch_assoc(mysqli_query(db(), '
    SELECT COUNT(DISTINCT d.id) c FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = "verified" AND u.status = "active"
      AND d.id IN (SELECT ds.doctor_id FROM doctor_specializations ds WHERE ds.specialization_id = ' . (int) $spec['id'] . ')
'))['c'];

$cities = mysqli_query(db(), "
    SELECT DISTINCT d.clinic_city AS city
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = 'verified' AND u.status = 'active'
      AND d.clinic_city IS NOT NULL AND d.clinic_city != ''
      AND d.id IN (SELECT ds.doctor_id FROM doctor_specializations ds WHERE ds.specialization_id = " . (int) $spec['id'] . ")
    ORDER BY city ASC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$intro = $spec['description'] ?: ('Board-certified ' . $spec['name'] . ' specialists offering online and in-person consultations.');

// Every answer here is specific to this specialty (its own description, its
// doctors' real fee range and cities) — generic copy repeated word-for-word
// on every specialty page is what AdSense/Google flag as replicated content.
$feeRange = mysqli_fetch_assoc(mysqli_query(db(), '
    SELECT MIN(NULLIF(d.consultation_fee_online, 0)) AS online_min, MAX(NULLIF(d.consultation_fee_online, 0)) AS online_max,
           MIN(NULLIF(d.consultation_fee_physical, 0)) AS clinic_min, MAX(NULLIF(d.consultation_fee_physical, 0)) AS clinic_max
    FROM doctors d JOIN users u ON u.id = d.user_id
    WHERE d.verification_status = "verified" AND u.status = "active"
      AND d.id IN (SELECT ds.doctor_id FROM doctor_specializations ds WHERE ds.specialization_id = ' . (int) $spec['id'] . ')
'));
$rangeText = fn($min, $max) => $min === null ? null : ((float) $min === (float) $max ? format_currency($min) : format_currency($min) . ' to ' . format_currency($max));
$onlineRange = $rangeText($feeRange['online_min'], $feeRange['online_max']);
$clinicRange = $rangeText($feeRange['clinic_min'], $feeRange['clinic_max']);
$siteName = get_setting('site_name', SITE_NAME);
$cityNames = array_column($cities, 'city');

$faqs = [];
if (trim((string) $spec['description']) !== '') {
    $faqs[] = ['q' => 'What does a ' . $spec['name'] . ' specialist treat?', 'a' => $spec['description']];
}
if ($onlineRange || $clinicRange) {
    $parts = [];
    if ($onlineRange) $parts[] = 'online consultations cost ' . $onlineRange;
    if ($clinicRange) $parts[] = 'in-clinic visits cost ' . $clinicRange;
    $faqs[] = ['q' => 'How much does a ' . $spec['name'] . ' consultation cost?', 'a' => 'Across the ' . $doctorCount . ' verified ' . $spec['name'] . ' doctor' . ($doctorCount == 1 ? '' : 's') . ' on ' . $siteName . ', ' . implode(' and ', $parts) . '. Each doctor sets their own fee, shown on their profile before you book.'];
}
if ($cityNames) {
    $cityList = count($cityNames) > 1 ? implode(', ', array_slice($cityNames, 0, -1)) . ' and ' . end($cityNames) : $cityNames[0];
    $faqs[] = ['q' => 'Where can I see a ' . $spec['name'] . ' doctor in person?', 'a' => 'Verified ' . $spec['name'] . ' doctors on ' . $siteName . ' currently practice in ' . $cityList . '. Filter by city above, or book an online consultation from anywhere.'];
}

// An empty specialty page has nothing for a searcher yet — keep it out of the index.
if ($doctorCount == 0) {
    $metaRobots = 'noindex, follow';
}

$pageTitle = $spec['name'] . ' Doctors — Book Online or In-Person | ' . get_setting('site_name', SITE_NAME);
$metaDescription = 'Find verified ' . $spec['name'] . ' specialists on ' . get_setting('site_name', SITE_NAME) . '. Compare fees and availability, then book an online or in-person consultation.' . ($doctorCount > 0 ? ' ' . $doctorCount . ' doctor' . ($doctorCount == 1 ? '' : 's') . ' available.' : '');
$canonical = APP_URL . '/specializations/' . $spec['slug'];
$breadcrumbs = [['name' => 'Home', 'url' => APP_URL . '/'], ['name' => 'Specializations', 'url' => APP_URL . '/specializations'], ['name' => $spec['name']]];
if ($faqs) {
    $extraHead = '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(
        fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]],
        $faqs
    )]) . '</script>';
}
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);padding-bottom:0;">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <a href="/specializations">Specializations</a> <i class="ri-arrow-right-s-line"></i> <span><?= e($spec['name']) ?></span></nav>
        <div class="section-head" style="margin-left:0;text-align:left;max-width:720px;" data-reveal>
            <span class="eyebrow"><i class="<?= e($spec['icon']) ?>"></i> Specialty</span>
            <h1><?= e($spec['name']) ?> Doctors</h1>
            <p><?= e($intro) ?> <?= (int) $doctorCount ?> verified <?= e($spec['name']) ?> specialist<?= $doctorCount == 1 ? '' : 's' ?> currently accepting patients on <?= e(get_setting('site_name', SITE_NAME)) ?>.</p>
        </div>
    </div>
</section>

<?php if (count($cities) > 0): ?>
<section class="section" style="padding-top:0;padding-bottom:0;">
    <div class="container">
        <p style="font-size:14px;color:var(--color-text-muted);">Available in: <?php foreach ($cities as $i => $c): ?><a href="/doctors?specialization=<?= e($spec['slug']) ?>&amp;city=<?= e($c['city']) ?>" style="color:var(--color-primary);font-weight:600;"><?= e($c['city']) ?></a><?= $i < count($cities) - 1 ? ', ' : '' ?><?php endforeach; ?></p>
    </div>
</section>
<?php endif; ?>

<section class="section">
    <div class="container">
        <h2 class="sr-only">Verified <?= e($spec['name']) ?> Doctors</h2>
        <?php if (!$doctors): ?>
        <div class="empty-state card">
            <i class="<?= e($spec['icon']) ?>"></i>
            <p style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--color-text);">No <?= e($spec['name']) ?> specialists yet</p>
            <p>Check back soon, or <a href="/doctors">browse all doctors</a>.</p>
        </div>
        <?php else: ?>
        <div class="grid grid-3 stagger">
            <?php foreach ($doctors as $d): require __DIR__ . '/includes/doctor-card.php'; endforeach; ?>
        </div>
        <?php if ($doctorCount > count($doctors)): ?>
        <div style="text-align:center;margin-top:44px;" data-reveal>
            <a href="/doctors?specialization=<?= e($spec['slug']) ?>" class="btn btn-primary">See All <?= e($spec['name']) ?> Doctors <i class="ri-arrow-right-line"></i></a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($faqs): ?>
<section class="section" style="background:var(--color-surface);">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="eyebrow">FAQ</span>
            <h2>Common questions about <?= e($spec['name']) ?> care</h2>
        </div>
        <div class="grid grid-<?= min(3, count($faqs)) ?> stagger">
            <?php foreach ($faqs as $f): ?>
            <div class="card" style="padding:24px;" data-reveal>
                <h3 style="font-size:16px;margin-bottom:8px;"><?= e($f['q']) ?></h3>
                <p style="color:var(--color-text-muted);font-size:14px;"><?= e($f['a']) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
