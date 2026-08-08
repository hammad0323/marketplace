<?php
require __DIR__ . '/config/config.php';

$specs = mysqli_query(db(), "
    SELECT s.*, (SELECT COUNT(*) FROM doctor_specializations ds JOIN doctors d ON d.id = ds.doctor_id WHERE ds.specialization_id = s.id AND d.verification_status='verified') AS doctor_count
    FROM specializations s WHERE s.is_active = 1 ORDER BY s.sort_order
");

$pageTitle = 'Medical Specializations — ' . SITE_NAME;
$metaDescription = 'Browse all medical specializations available on ' . SITE_NAME . ' and find the right doctor for your needs.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Specializations</span></nav>
        <div class="section-head" style="margin-left:0;text-align:left;max-width:600px;" data-reveal>
            <span class="eyebrow">Browse by Specialty</span>
            <h1>All Specializations</h1>
            <p>Choose a specialty to see verified, board-certified doctors accepting new patients.</p>
        </div>
        <div class="grid grid-4 stagger">
            <?php while ($s = mysqli_fetch_assoc($specs)): ?>
            <a href="/doctors?specialization=<?= e($s['slug']) ?>" class="card card-hover spec-card" data-reveal data-tilt>
                <div class="icon"><i class="<?= e($s['icon']) ?>"></i></div>
                <h4><?= e($s['name']) ?></h4>
                <p><?= (int)$s['doctor_count'] ?> doctor<?= $s['doctor_count'] == 1 ? '' : 's' ?></p>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
