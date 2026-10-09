<?php /** @var array $images @var array $p */ if (!$images) { $images = [['file_path' => null, 'alt_text' => $p['name'], 'caption' => null, 'id' => 0]]; } ?>
<div class="gallery" data-gallery>
  <div class="gallery__main">
    <?php foreach ($images as $i => $img): ?>
      <figure class="gallery__slide<?= $i === 0 ? ' is-active' : '' ?>" data-gallery-slide="<?= $i ?>">
        <div class="gallery__zoom" data-zoom="<?= e(media_url($img['file_path'])) ?>">
          <img src="<?= e(media_url($img['file_path'])) ?>" alt="<?= e($img['alt_text'] ?: $p['name']) ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> width="1200" height="1500">
        </div>
        <?php if (!empty($img['caption'])): ?><figcaption><?= e($img['caption']) ?></figcaption><?php endif; ?>
      </figure>
    <?php endforeach; ?>
    <?php if (count($images) > 1): ?>
      <button type="button" class="gallery__arrow gallery__arrow--prev" data-gallery-prev aria-label="Previous image"><i class="bi bi-chevron-left"></i></button>
      <button type="button" class="gallery__arrow gallery__arrow--next" data-gallery-next aria-label="Next image"><i class="bi bi-chevron-right"></i></button>
    <?php endif; ?>
    <button type="button" class="gallery__expand" data-lightbox-open aria-label="Open full-screen gallery"><i class="bi bi-arrows-fullscreen"></i></button>
  </div>
  <?php if (count($images) > 1): ?>
    <div class="gallery__thumbs" role="tablist" aria-label="Product images">
      <?php foreach ($images as $i => $img): ?>
        <button type="button" class="gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>" data-gallery-thumb="<?= $i ?>" aria-label="Show image <?= $i + 1 ?>">
          <img src="<?= e(media_url($img['file_path'])) ?>" alt="" loading="lazy" width="160" height="200">
        </button>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
