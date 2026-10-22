<?php
seo_set(['title' => 'Collections', 'description' => "Shop men's fashion by style: everyday essentials, smart casual, business & formal, streetwear, traditional and more.", 'breadcrumbs' => [['Home', url()], ['Collections', url('collections')]]]);
$items = collections_list(false, 100);
$bodyClass = 'page-collections';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-listing-hero">
    <div class="uc-container uc-listing-hero-inner">
        <?= breadcrumbs_html([['Home', url()], ['Collections', null]]) ?>
        <h1 class="uc-page-title">Collections</h1>
        <p class="uc-page-intro">Complete looks curated for every part of your life.</p>
    </div>
</section>
<section class="uc-container uc-section pt-4">
    <div class="uc-colls uc-colls--grid">
        <div class="uc-colls-track">
            <?php foreach ($items as $i => $c): ?>
                <a class="uc-coll-card" href="<?= e(collection_url($c)) ?>" data-reveal="fade-up">
                    <img src="<?= e(img_sized($c['image'], 900)) ?>" alt="<?= e($c['name']) ?>" loading="lazy">
                    <span class="uc-coll-info"><span class="uc-coll-index"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><span class="uc-coll-name"><?= e($c['name']) ?></span>
                        <?php if ($c['description']): ?><span class="uc-coll-desc"><?= e(str_limit($c['description'], 90)) ?></span><?php endif; ?><span class="uc-coll-cta">Explore <i class="bi bi-arrow-right"></i></span></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
