<?php
$cards = [];
foreach ([1, 2] as $n) {
    if (!empty($s['card' . $n . '_title'])) {
        $cards[] = ['image' => $s['card' . $n . '_image'], 'eyebrow' => $s['card' . $n . '_eyebrow'], 'title' => $s['card' . $n . '_title'], 'text' => $s['card' . $n . '_text'],
            'points' => array_filter(array_map('trim', explode("\n", (string) $s['card' . $n . '_points']))), 'button_text' => $s['card' . $n . '_button_text'], 'button_url' => $s['card' . $n . '_button_url']];
    }
}
if (!$cards) {
    return;
}
?>
<section <?= section_wrapper_attrs($s, 'services', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <?= section_heading($s, 'center') ?>
        <div class="uc-services">
            <?php foreach ($cards as $i => $c): ?>
                <article class="uc-service-card"<?= reveal($s, $i * 120) ?>>
                    <div class="uc-service-img"><?php if ($c['image']): ?><img src="<?= e(img_sized($c['image'], 1100)) ?>" alt="" loading="lazy" data-parallax="0.05"><?php endif; ?></div>
                    <div class="uc-service-body">
                        <?php if ($c['eyebrow']): ?><p class="uc-eyebrow is-light"><?= e($c['eyebrow']) ?></p><?php endif; ?>
                        <h3 class="uc-service-title"><?= e($c['title']) ?></h3>
                        <?php if ($c['text']): ?><p><?= e($c['text']) ?></p><?php endif; ?>
                        <?php if ($c['points']): ?><ul><?php foreach ($c['points'] as $pt): ?><li><i class="bi bi-check2"></i> <?= e($pt) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <?php if ($c['button_text']): ?><a class="uc-btn uc-btn--light" href="<?= e(url($c['button_url'] ?: '')) ?>"><?= e($c['button_text']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
