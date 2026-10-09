<?php if (!defined('EBAYA')) { http_response_code(403); exit; }
$bc = $GLOBALS['_seo']['breadcrumbs'] ?? [];
if (count($bc) > 1): ?>
<nav aria-label="Breadcrumb" class="breadcrumbs">
  <ol>
    <?php foreach ($bc as $i => [$label, $u]): ?>
      <li><?php if ($u && $i < count($bc) - 1): ?><a href="<?= e($u) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>
</nav>
<?php endif;
