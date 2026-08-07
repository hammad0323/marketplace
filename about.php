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
require __DIR__ . '/includes/header.php';

$totalDoctors = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM doctors WHERE verification_status='verified'"))['c'];
$totalPatients = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM patients"))['c'];
$totalAppointments = mysqli_fetch_assoc(mysqli_query(db(), "SELECT COUNT(*) c FROM appointments"))['c'];
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/index.php">Home</a> <i class="ri-arrow-right-s-line"></i> <span><?= e($page['title']) ?></span></nav>
        <div style="max-width:760px;" data-reveal>
            <span class="eyebrow">About Us</span>
            <h1 style="font-size:36px;margin-bottom:20px;"><?= e($page['title']) ?></h1>
            <div style="font-size:16px;color:var(--color-text-muted);line-height:1.9;"><?= $page['content'] ?></div>
        </div>

        <div class="grid grid-3 stagger" style="margin-top:56px;">
            <div class="card card-hover" style="padding:28px;text-align:center;" data-reveal>
                <b class="counter" data-counter="<?= (int)$totalDoctors ?>" style="font-size:34px;display:block;color:var(--color-primary);">0</b>
                <span style="color:var(--color-text-muted);">Verified Doctors</span>
            </div>
            <div class="card card-hover" style="padding:28px;text-align:center;" data-reveal>
                <b class="counter" data-counter="<?= (int)$totalPatients ?>" style="font-size:34px;display:block;color:var(--color-primary);">0</b>
                <span style="color:var(--color-text-muted);">Registered Patients</span>
            </div>
            <div class="card card-hover" style="padding:28px;text-align:center;" data-reveal>
                <b class="counter" data-counter="<?= (int)$totalAppointments ?>" style="font-size:34px;display:block;color:var(--color-primary);">0</b>
                <span style="color:var(--color-text-muted);">Appointments Booked</span>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
