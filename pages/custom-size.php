<?php
/** Custom Size landing: how it works, choose a garment, size charts. */
$garments = studio_garments();
seo_set([
    'title' => 'Custom Size — Ready-made Style in Your Exact Measurements',
    'description' => 'Get shalwar qameez, kurtas, office shirts and women\'s suits stitched to your measurements at ready-made prices. Choose colour, collar, cuffs and shalwar style.',
    'breadcrumbs' => [['Home', url()], ['Custom Size', url('custom-size')]],
]);
$bodyClass = 'page-custom-size';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-feature-hero">
    <div class="uc-container uc-feature-hero-inner">
        <?= breadcrumbs_html([['Home', url()], ['Custom Size', null]]) ?>
        <p class="uc-eyebrow">Custom Size Studio</p>
        <h1 class="uc-display">Ready-made style.<br>Made to <em>your</em> measurements.</h1>
        <p class="uc-page-intro">Pick a shalwar qameez, kurta, office shirt or ladies suit, choose your colour, collar, cuffs and shalwar or pajama — and we stitch it to your size at the ready-made price.</p>
        <div class="d-flex gap-2 flex-wrap"><a class="uc-btn uc-btn--light" href="#choose">Start customising</a><a class="uc-btn uc-btn--outline-light" href="<?= e(url('measurement-guide')) ?>">How to measure</a></div>
    </div>
</section>

<section class="uc-section">
    <div class="uc-container">
        <ol class="uc-steps4">
            <li data-reveal="fade-up"><i class="bi bi-hanger"></i><strong>Choose a design</strong><span>Any product marked “Custom size available”.</span></li>
            <li data-reveal="fade-up" style="--reveal-delay:80ms"><i class="bi bi-palette"></i><strong>Colour &amp; style</strong><span>Collar, cuffs, daman, shalwar / pajama — see it live on the mannequin.</span></li>
            <li data-reveal="fade-up" style="--reveal-delay:160ms"><i class="bi bi-rulers"></i><strong>Your measurements</strong><span>Start from S–XXL or our height &amp; weight finder, then fine-tune.</span></li>
            <li data-reveal="fade-up" style="--reveal-delay:240ms"><i class="bi bi-truck"></i><strong>Stitched &amp; delivered</strong><span>Ready in a few working days, delivered anywhere in Pakistan.</span></li>
        </ol>
    </div>
</section>

<section class="uc-section pt-0" id="choose">
    <div class="uc-container">
        <header class="uc-section-head text-center"><p class="uc-eyebrow">Step 1</p><h2 class="uc-section-title">Choose what to customise</h2></header>
        <?php if (!$garments): ?>
            <p class="text-center text-muted">Custom sizing is coming soon.</p>
        <?php endif; ?>
        <ul class="nav uc-tabs justify-content-center" role="tablist">
            <?php foreach ($garments as $i => $g): ?>
                <li class="nav-item"><button class="nav-link<?= $i === 0 ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#g-<?= e($g['code']) ?>" type="button"><?= e($g['name']) ?></button></li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content">
            <?php foreach ($garments as $i => $g):
                $items = studio_products(12, (int) $g['id']);
                $def = studio_definitions()[$g['code']]; ?>
                <div class="tab-pane fade<?= $i === 0 ? ' show active' : '' ?>" id="g-<?= e($g['code']) ?>" role="tabpanel">
                    <?php if ($g['intro']): ?><p class="text-center text-muted mb-4"><?= e($g['intro']) ?></p><?php endif; ?>
                    <?php if ($items): ?>
                        <div class="uc-grid uc-grid--4">
                            <?php foreach ($items as $p): ?>
                                <article class="uc-card">
                                    <div class="uc-card-media"><a class="uc-card-img" href="<?= e(url('custom-size/' . $p['slug'])) ?>"><img src="<?= e(img_sized($p['image'], 700)) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></a>
                                        <span class="uc-badge badge-premium">Custom size</span></div>
                                    <div class="uc-card-body">
                                        <h3 class="uc-card-title"><a href="<?= e(url('custom-size/' . $p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
                                        <div class="uc-price"><span class="uc-price-now"><?= e(money($p['price_info']['final'])) ?></span><?php if ((float) $g['fee'] <= 0): ?><small class="text-muted">same price, your size</small><?php endif; ?></div>
                                        <a class="uc-btn uc-btn--sm uc-btn--block mt-2" href="<?= e(url('custom-size/' . $p['slug'])) ?>"><i class="bi bi-rulers"></i> Customise size</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">No products available for custom sizing in this range yet.</p>
                    <?php endif; ?>
                    <div class="uc-card-box mt-5">
                        <h3 class="h5">Standard size chart — <?= e($g['name']) ?></h3>
                        <?php partial('components/size-chart', ['def' => $def, 'chart' => studio_chart((int) $g['id']), 'fitTable' => studio_fit_table((int) $g['id'])]); ?>
                        <p class="small mt-2 mb-0">Production time: <?= (int) $g['production_days_min'] ?>–<?= (int) $g['production_days_max'] ?> working days<?= (float) $g['fee'] > 0 ? ' · custom sizing charge ' . e(money($g['fee'])) : ' · no extra charge for custom size' ?>.</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="uc-section uc-section-sand">
    <div class="uc-container uc-faq-mini">
        <header class="uc-section-head text-center"><h2 class="uc-section-title">Questions</h2></header>
        <div class="uc-accordion">
            <details><summary>Does custom size cost more?</summary><p>Most designs are made to your size at the same ready-made price. If a style option has an extra charge (for example French cuffs), it is shown before you add to bag.</p></details>
            <details><summary>What if I don't know my measurements?</summary><p>Choose the size you usually buy (S–XXL) or use the height &amp; weight finder — we pre-fill standard measurements. Our <a href="<?= e(url('measurement-guide')) ?>">measuring guide</a> shows exactly where to measure; you can also measure a qameez that fits you well.</p></details>
            <details><summary>How long does it take?</summary><p>Production time is shown on each garment (usually 4–7 working days), plus delivery time to your city. Urgent delivery is not available for custom-made pieces.</p></details>
            <details><summary>What if it doesn't fit?</summary><p>If your piece doesn't match the measurements you gave, contact us and we will adjust it. For other changes, use our <a href="<?= e(url('alterations')) ?>">alteration service</a>.</p></details>
        </div>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
