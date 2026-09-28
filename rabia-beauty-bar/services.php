<?php
require __DIR__ . '/config.php';

$categories = q('SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
$byCat = [];
foreach (q('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll() as $s) {
    $byCat[$s['category_id']][] = $s;
}

$pageTitle  = 'Services & Prices';
$activeNav  = 'services';
$heroTitle  = 'Services & Prices';
$heroScript = 'Pamper yourself';
$heroText   = 'Hair, makeup, nails, skin and relaxation — explore everything we offer and book in a few clicks.';
$heroImage  = 'assets/img/bridal-bun.jpg';
require __DIR__ . '/inc/header.php';
require __DIR__ . '/inc/page-hero.php';
?>
<section class="section">
    <div class="container">
        <div class="tabs reveal">
            <?php foreach ($categories as $c): if (empty($byCat[$c['id']])) continue; ?>
                <a class="tab" href="#<?= e($c['slug']) ?>"><i class="fa-solid <?= e($c['icon']) ?>"></i> <?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </div>

        <?php foreach ($categories as $c): if (empty($byCat[$c['id']])) continue; ?>
            <div class="cat-block" id="<?= e($c['slug']) ?>">
                <div class="cat-head reveal">
                    <div class="service-icon"><i class="fa-solid <?= e($c['icon']) ?>"></i></div>
                    <div><h2><?= e($c['name']) ?></h2><p><?= e($c['description']) ?></p></div>
                </div>
                <div class="svc-grid">
                    <?php foreach ($byCat[$c['id']] as $i => $s): ?>
                        <div class="svc-item reveal" data-delay="<?= ($i % 4) * 80 ?>">
                            <?php if ($s['is_featured']): ?><span class="badge">Popular</span><?php endif; ?>
                            <h3><?= e($s['name']) ?></h3>
                            <?php if ($s['description']): ?><p><?= e($s['description']) ?></p><?php endif; ?>
                            <?php if ($s['duration']): ?><p><i class="fa-regular fa-clock"></i> <?= e($s['duration']) ?></p><?php endif; ?>
                            <div class="svc-foot">
                                <span class="pl-price"><?= $s['price_from'] && $s['price'] ? 'From ' : '' ?><?= money($s['price']) ?></span>
                                <a class="link-arrow" href="booking.php?service=<?= $s['id'] ?>">Book <i class="fa-solid fa-arrow-right"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$byCat): ?><p class="empty">Our service menu is being updated — please call us on <?= e(setting('phone')) ?>.</p><?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/inc/footer.php'; ?>
