<?php ?>
<section <?= section_wrapper_attrs($s, 'rich_text', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <div class="uc-richblock text-<?= e($s['text_align']) ?>"<?= reveal($s) ?>>
            <?php if ($s['eyebrow']): ?><p class="uc-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <?php if ($s['title']): ?><h2 class="uc-section-title"><?= e($s['title']) ?></h2><?php endif; ?>
            <div class="uc-rich"><?= sanitize_html($s['text']) ?></div>
            <?php if ($s['button_text']): ?><a class="uc-btn" href="<?= e(url($s['button_url'] ?: 'shop')) ?>"><?= e($s['button_text']) ?></a><?php endif; ?>
        </div>
    </div>
</section>
