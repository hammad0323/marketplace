<?php $crumbs = meta('breadcrumbs', []); if ($crumbs): ?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
  <ol>
    <?php foreach ($crumbs as $i => $c): ?>
      <?php if ($i < count($crumbs) - 1 && !empty($c['url'])): ?>
        <li><a href="<?= e($c['url']) ?>"><?= e($c['name']) ?></a></li>
      <?php else: ?>
        <li aria-current="page"><?= e($c['name']) ?></li>
      <?php endif; ?>
    <?php endforeach; ?>
  </ol>
</nav>
<?php endif; ?>
