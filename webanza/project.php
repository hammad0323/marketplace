<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$project = row('SELECT * FROM portfolio WHERE slug = ? AND is_active = 1', [$_GET['slug'] ?? '']);
if (!$project) {
    require __DIR__ . '/404.php';
    exit;
}
$more = active('portfolio', 'AND id <> ? ORDER BY is_featured DESC, sort_order LIMIT 2', [$project['id']]);

$page_title = $project['title'];
$page_desc  = $project['summary'];
$page_image = $project['image'];
require __DIR__ . '/partials/header.php';

page_hero($project['title'], (string) $project['summary'], ['Portfolio' => 'portfolio.php', $project['title'] => ''], (string) $project['category']);
?>
<section class="section">
  <div class="container detail-grid">
    <div>
      <div class="detail-cover" data-reveal="zoom"><?= thumb($project['image'], $project['title'], (string) $project['client'], portfolio_icon((string) $project['category'])) ?></div>
      <div class="prose" data-reveal="up"><?= rich($project['description']) ?></div>
    </div>
    <aside>
      <div class="sidebar-box" data-reveal="right">
        <h4>Project Details</h4>
        <ul class="meta-list">
          <?php if ($project['client']): ?><li><span>Client</span><strong><?= e($project['client']) ?></strong></li><?php endif; ?>
          <?php if ($project['category']): ?><li><span>Category</span><strong><?= e($project['category']) ?></strong></li><?php endif; ?>
          <li><span>Year</span><strong><?= e(date('Y', strtotime($project['created_at']))) ?></strong></li>
        </ul>
        <?php if ($project['tech']): ?>
          <h4 style="margin-top:24px">Technologies</h4>
          <div class="tags"><?php foreach (array_filter(array_map('trim', explode(',', $project['tech']))) as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($project['project_url']): ?>
          <a href="<?= e($project['project_url']) ?>" target="_blank" rel="noopener" class="btn btn-block" style="margin-top:26px">Visit Live Project <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
        <?php endif; ?>
      </div>
      <div class="sidebar-box dark-box" data-reveal="right" style="--d:.1s">
        <h4>Want results like these?</h4>
        <p>Let's talk about your project today.</p>
        <a href="<?= e(url('contact.php')) ?>" class="btn btn-block">Start a Project</a>
      </div>
    </aside>
  </div>
</section>
<?php if ($more): ?>
<section class="section" style="padding-top:0">
  <div class="container">
    <div class="section-head left"><span class="eyebrow">More Work</span><?= split_heading('More *projects*') ?></div>
    <div class="pf-grid"><?php foreach ($more as $i => $p) { portfolio_item($p, $i); } ?></div>
  </div>
</section>
<?php endif;
section_cta();
require __DIR__ . '/partials/footer.php';
