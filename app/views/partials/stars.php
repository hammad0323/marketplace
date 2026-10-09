<?php /** @var float $rating */ $r = (float) $rating; ?>
<span class="stars" aria-label="Rated <?= e(number_format($r, 1)) ?> out of 5">
  <?php for ($i = 1; $i <= 5; $i++): ?><i class="bi <?= $r >= $i ? 'bi-star-fill' : ($r >= $i - 0.5 ? 'bi-star-half' : 'bi-star') ?>"></i><?php endfor; ?>
</span>
