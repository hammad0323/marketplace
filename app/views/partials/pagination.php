<?php /** @var array $pg */ if ($pg['pages'] > 1): ?>
<nav class="pager" aria-label="Pagination">
  <?php if ($pg['page'] > 1): ?><a class="pager__link" rel="prev" href="<?= e(query_with(['page' => $pg['page'] - 1])) ?>" aria-label="Previous page"><i class="bi bi-arrow-left"></i></a><?php endif; ?>
  <?php
  $start = max(1, $pg['page'] - 2);
  $end = min($pg['pages'], $pg['page'] + 2);
  if ($start > 1) { echo '<a class="pager__link" href="' . e(query_with(['page' => 1])) . '">1</a>'; if ($start > 2) echo '<span class="pager__gap">…</span>'; }
  for ($i = $start; $i <= $end; $i++): ?>
    <a class="pager__link<?= $i === $pg['page'] ? ' is-current' : '' ?>" href="<?= e(query_with(['page' => $i])) ?>"<?= $i === $pg['page'] ? ' aria-current="page"' : '' ?>><?= $i ?></a>
  <?php endfor;
  if ($end < $pg['pages']) { if ($end < $pg['pages'] - 1) echo '<span class="pager__gap">…</span>'; echo '<a class="pager__link" href="' . e(query_with(['page' => $pg['pages']])) . '">' . $pg['pages'] . '</a>'; } ?>
  <?php if ($pg['page'] < $pg['pages']): ?><a class="pager__link" rel="next" href="<?= e(query_with(['page' => $pg['page'] + 1])) ?>" aria-label="Next page"><i class="bi bi-arrow-right"></i></a><?php endif; ?>
</nav>
<?php endif; ?>
