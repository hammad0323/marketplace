<?php ?>
<section <?= section_wrapper_attrs($s, 'newsletter', $sid) ?>>
    <?php if ($s['image']): ?><div class="uc-news-bg"><img src="<?= e(img_url($s['image'])) ?>" alt="" loading="lazy"></div><?php endif; ?>
    <div class="<?= section_container_class($s) ?>">
        <div class="uc-newsletter"<?= reveal($s) ?>>
            <?php if ($s['eyebrow']): ?><p class="uc-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <?php if ($s['title']): ?><h2 class="uc-display"><?= e($s['title']) ?></h2><?php endif; ?>
            <?php if ($s['text']): ?><p class="uc-news-text"><?= e($s['text']) ?></p><?php endif; ?>
            <form class="uc-news-form uc-news-form--big" data-newsletter-form novalidate>
                <div class="uc-news-inline">
                    <input type="email" name="email" placeholder="Enter your email address" aria-label="Email address" required>
                    <button type="submit" class="uc-btn uc-btn--accent"><?= e($s['button_text'] ?: 'Subscribe') ?></button>
                </div>
                <label class="uc-check"><input type="checkbox" name="consent" value="1" required> <span><?= e($s['consent_text'] ?: 'I agree to receive marketing emails.') ?></span></label>
                <input type="hidden" name="source" value="home">
            </form>
        </div>
    </div>
</section>
