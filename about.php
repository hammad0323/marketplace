<?php
require __DIR__ . '/config/config.php';

$stmt = mysqli_prepare(db(), 'SELECT * FROM cms_pages WHERE slug = ? LIMIT 1');
$slug = 'about';
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$page = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

if (!$page) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pageTitle = $page['meta_title'] ?: ($page['title'] . ' — ' . SITE_NAME);
$metaDescription = $page['meta_description'] ?: excerpt($page['content'], 155);
$breadcrumbs = [['name' => 'Home', 'url' => APP_URL . '/'], ['name' => $page['title']]];
// Content-rich enough to carry ads (see includes/header.php).
$adsEligible = content_word_count($page['content']) >= 150;
require __DIR__ . '/includes/header.php';

$totalDoctors = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM doctors WHERE verification_status='verified'"))['c'];
$totalPatients = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM patients"))['c'];
$totalAppointments = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM appointments"))['c'];
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span><?= e($page['title']) ?></span></nav>
        <div style="max-width:760px;" data-reveal>
            <span class="eyebrow">About Us</span>
            <h1 style="font-size:36px;margin-bottom:20px;"><?= e($page['title']) ?></h1>
            <div style="font-size:16px;color:var(--color-text-muted);line-height:1.9;"><?= $page['content'] ?></div>
        </div>

        <?php
        // Real numbers only, written into the HTML (not a "0" waiting for JS),
        // and patient/appointment totals only once they're big enough to help.
        $totalSpecs = (int) mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM specializations WHERE is_active = 1"))['c'];
        $aboutStats = [[(int) $totalDoctors, 'Verified Doctors'], [$totalSpecs, 'Specialties']];
        if ($totalPatients >= 100) $aboutStats[] = [(int) $totalPatients, 'Registered Patients'];
        if ($totalAppointments >= 100) $aboutStats[] = [(int) $totalAppointments, 'Appointments Booked'];
        ?>
        <div class="grid grid-<?= count($aboutStats) ?> stagger" style="margin-top:56px;">
            <?php foreach ($aboutStats as [$value, $label]): ?>
            <div class="card card-hover" style="padding:28px;text-align:center;" data-reveal>
                <b class="counter" data-counter="<?= $value ?>" style="font-size:34px;display:block;color:var(--color-primary);"><?= number_format($value) ?></b>
                <span style="color:var(--color-text-muted);"><?= e($label) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
