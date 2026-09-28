<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$perPage = 9;
$pageNo  = max(1, (int) ($_GET['p'] ?? 1));
$total   = (int) val('SELECT COUNT(*) FROM posts WHERE is_active = 1');
$pages   = max(1, (int) ceil($total / $perPage));
$posts   = active('posts', 'ORDER BY published_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($pageNo - 1) * $perPage));

$page_title = 'Blog';
$page_desc  = 'Insights on e-commerce, SaaS, mobile apps, design, SEO and Google marketing from the ' . setting('site_name') . ' team.';
require __DIR__ . '/partials/header.php';

page_hero('Insights & *News*', 'Practical tips on building, launching and growing your business online.', ['Blog' => ''], 'Blog');
?>
<section class="section">
  <div class="container">
    <div class="grid g-3">
      <?php foreach ($posts as $i => $p) { post_card($p, $i); } ?>
    </div>
    <?php if (!$posts): ?><p class="center muted">No articles yet — check back soon.</p><?php endif; ?>
    <?php if ($pages > 1): ?>
      <div class="pkg-tabs" style="margin-top:60px">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a class="pkg-tab<?= $i === $pageNo ? ' active' : '' ?>" href="<?= e(url('blog.php?p=' . $i)) ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php
section_cta();
require __DIR__ . '/partials/footer.php';
