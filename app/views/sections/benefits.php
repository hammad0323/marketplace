<?php $items = array_values(array_filter((array) $s['items'], fn($i) => trim($i['title'] ?? '') !== '')); if (!$items) { return; } ?>
<section <?= section_attrs($s, 'section--benefits') ?>>
  <div class="<?= e(section_container_class($s)) ?>">
    <?php view('sections/_heading', ['s' => $s, 'center' => true]); ?>
    <div class="benefits benefits--n<?= count($items) ?>">
      <?php foreach ($items as $i => $b): ?>
        <div class="benefit"<?= reveal_attr($s, $i * 90) ?>>
          <span class="benefit__icon"><i class="bi bi-<?= e(preg_replace('/[^a-z0-9-]/', '', strtolower($b['icon'] ?? 'gem')) ?: 'gem') ?>"></i></span>
          <h3 class="benefit__title"><?= e($b['title']) ?></h3>
          <?php if (!empty($b['text'])): ?><p class="benefit__text"><?= e($b['text']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
