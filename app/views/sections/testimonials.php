<?php $items = active_testimonials(max(1, min(20, (int) $s['limit']))); if (!$items) { return; } ?>
<section <?= section_attrs($s, 'section--testimonials') ?>>
  <div class="<?= e(section_container_class($s)) ?>">
    <?php view('sections/_heading', ['s' => $s, 'center' => true]); ?>
    <div class="quotes" data-quotes<?= reveal_attr($s, 100) ?>>
      <div class="quotes__track">
        <?php foreach ($items as $i => $t): ?>
          <figure class="quote<?= $i === 0 ? ' is-active' : '' ?>">
            <?php if (!empty($s['show_rating']) && $t['rating']): partial('stars', ['rating' => (float) $t['rating']]); endif; ?>
            <blockquote><p>“<?= e($t['quote']) ?>”</p></blockquote>
            <figcaption><strong><?= e($t['author_name']) ?></strong><?php if ($t['author_meta']): ?><span><?= e($t['author_meta']) ?></span><?php endif; ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
      <?php if (count($items) > 1): ?>
        <div class="quotes__dots">
          <?php foreach ($items as $i => $t): ?><button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-quote-dot="<?= $i ?>" aria-label="Show testimonial <?= $i + 1 ?>"></button><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
