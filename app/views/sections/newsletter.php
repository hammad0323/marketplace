<section <?= section_attrs($s, 'section--newsletter' . ($s['image'] ? ' has-bg' : '')) ?>>
  <?php if ($s['image']): ?><div class="newsletter__bg" data-parallax-box><img src="<?= e(media_url($s['image'])) ?>" alt="" loading="lazy" data-parallax="0.15"></div><?php endif; ?>
  <div class="container container--narrow">
    <div class="newsletter"<?= reveal_attr($s) ?>>
      <h2 class="section-title"><?= e($s['title']) ?></h2>
      <?php if ($s['text']): ?><p class="section-sub"><?= e($s['text']) ?></p><?php endif; ?>
      <form class="newsletter__form" data-newsletter-form novalidate>
        <input type="hidden" name="source" value="homepage">
        <div class="newsletter__row">
          <label class="visually-hidden" for="nl-email-<?= e($key) ?>">Email address</label>
          <input type="email" id="nl-email-<?= e($key) ?>" name="email" placeholder="Your email address" required maxlength="190" autocomplete="email">
          <button type="submit" class="btn-lux"><?= e($s['button_label'] ?: 'Subscribe') ?></button>
        </div>
        <label class="newsletter__consent">
          <input type="checkbox" name="consent" value="1" required>
          <span><?= e($s['consent_text']) ?> <a href="<?= e(path_url('privacy-policy')) ?>">Privacy policy</a>.</span>
        </label>
        <div class="hp-field" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
      </form>
    </div>
  </div>
</section>
