<?php
require __DIR__ . '/config.php';

$courses = q('SELECT * FROM courses WHERE is_active = 1 ORDER BY sort_order, id DESC')->fetchAll();

$pageTitle  = 'Beauty Academy';
$activeNav  = 'courses';
$heroTitle  = 'Beauty Academy';
$heroScript = 'Build your skills, create your future';
$heroText   = 'Professional, hands-on courses in makeup, hairstyling and nails — taught by Rabia Khan in small batches.';
$heroImage  = 'assets/img/brushes.jpg';
require __DIR__ . '/inc/header.php';
require __DIR__ . '/inc/page-hero.php';
?>
<section class="section academy">
    <div class="academy-bg" data-parallax="0.2"></div>
    <div class="container">
        <?php if (!$courses): ?>
            <p class="empty">New courses are coming soon. Call or WhatsApp <?= e(setting('phone')) ?> to join the waiting list.</p>
        <?php endif; ?>
        <div class="course-grid">
            <?php foreach ($courses as $i => $c): ?>
                <article class="course-card reveal" data-delay="<?= ($i % 3) * 150 ?>">
                    <a href="course.php?slug=<?= e($c['slug']) ?>" class="course-img">
                        <img src="<?= img($c['image']) ?>" alt="<?= e($c['title']) ?>" loading="lazy">
                        <?php if ($c['duration']): ?><span class="course-pill"><i class="fa-regular fa-clock"></i> <?= e($c['duration']) ?></span><?php endif; ?>
                    </a>
                    <div class="course-body">
                        <h3><a href="course.php?slug=<?= e($c['slug']) ?>"><?= e($c['title']) ?></a></h3>
                        <p><?= e($c['tagline']) ?></p>
                        <ul class="course-meta">
                            <?php if ($c['schedule']): ?><li><i class="fa-regular fa-calendar"></i> <?= e($c['schedule']) ?></li><?php endif; ?>
                            <?php if ($c['timing']): ?><li><i class="fa-regular fa-clock"></i> <?= e($c['timing']) ?></li><?php endif; ?>
                            <?php if ($c['start_date']): ?><li><i class="fa-solid fa-flag"></i> Starts <?= e($c['start_date']) ?></li><?php endif; ?>
                            <?php if ($c['extras']): ?><li><i class="fa-solid fa-award"></i> <?= e($c['extras']) ?></li><?php endif; ?>
                        </ul>
                        <div class="course-foot">
                            <span class="fee"><?= money($c['fee'], 'Contact us') ?></span>
                            <a href="course.php?slug=<?= e($c['slug']) ?>#enroll" class="btn btn-primary btn-sm">Enroll Now</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
