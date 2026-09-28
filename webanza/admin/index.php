<?php
require __DIR__ . '/inc.php';

$tiles = [
    ['New inquiries', (int) val("SELECT COUNT(*) FROM inquiries WHERE status = 'new'"), 'fa-solid fa-inbox', 'inquiries.php?status=new'],
    ['Total inquiries', (int) val('SELECT COUNT(*) FROM inquiries'), 'fa-solid fa-envelope-open-text', 'inquiries.php'],
    ['Packages', (int) val('SELECT COUNT(*) FROM packages WHERE is_active = 1'), 'fa-solid fa-tags', 'manage.php?e=packages'],
    ['Services', (int) val('SELECT COUNT(*) FROM services WHERE is_active = 1'), 'fa-solid fa-layer-group', 'manage.php?e=services'],
    ['Projects', (int) val('SELECT COUNT(*) FROM portfolio WHERE is_active = 1'), 'fa-solid fa-briefcase', 'manage.php?e=portfolio'],
    ['Blog posts', (int) val('SELECT COUNT(*) FROM posts WHERE is_active = 1'), 'fa-solid fa-newspaper', 'manage.php?e=posts'],
    ['Subscribers', (int) val('SELECT COUNT(*) FROM subscribers'), 'fa-solid fa-at', 'subscribers.php'],
    ['Won deals', (int) val("SELECT COUNT(*) FROM inquiries WHERE status = 'won'"), 'fa-solid fa-trophy', 'inquiries.php?status=won'],
];
$recent = rows('SELECT * FROM inquiries ORDER BY created_at DESC LIMIT 8');
$popular = rows('SELECT package_name, COUNT(*) n FROM inquiries WHERE package_name IS NOT NULL GROUP BY package_name ORDER BY n DESC LIMIT 5');

admin_header('Dashboard', 'dashboard');
?>
<div class="welcome">
  <div>
    <h2>Hello, <?= e(explode(' ', admin()['name'])[0]) ?> 👋</h2>
    <p>Here's what's happening on <?= e(setting('site_name')) ?> today.</p>
  </div>
  <div class="welcome-actions">
    <a class="btn btn-primary" href="manage.php?e=packages&amp;action=edit"><i class="fa-solid fa-plus"></i> New package</a>
    <a class="btn btn-light" href="manage.php?e=posts&amp;action=edit"><i class="fa-solid fa-pen"></i> Write a post</a>
  </div>
</div>

<div class="tiles">
  <?php foreach ($tiles as [$label, $n, $icon, $href]): ?>
    <a class="tile" href="<?= e($href) ?>">
      <span class="tile-icon"><i class="<?= e($icon) ?>"></i></span>
      <span class="tile-num"><?= number_format($n) ?></span>
      <span class="tile-label"><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="cols">
  <div class="card">
    <div class="card-head"><h3>Latest inquiries &amp; orders</h3><a href="inquiries.php">View all <i class="fa-solid fa-arrow-right"></i></a></div>
    <?php if (!$recent): ?>
      <p class="empty">No inquiries yet. They will appear here when visitors use the contact or order form.</p>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Name</th><th>Interested in</th><th>Status</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr class="clickable" onclick="location.href='inquiries.php?view=<?= (int) $r['id'] ?>'">
            <td><strong><?= e($r['name']) ?></strong><br><small class="muted"><?= e($r['email']) ?></small></td>
            <td><?= e($r['package_name'] ?: ($r['service'] ?: '—')) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="muted"><?= e(date('M j, g:ia', strtotime($r['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <div>
    <div class="card">
      <div class="card-head"><h3>Quick edit</h3></div>
      <div class="quick">
        <a href="settings.php?tab=hero"><i class="fa-solid fa-wand-magic-sparkles"></i> Hero section</a>
        <a href="settings.php?tab=general"><i class="fa-solid fa-image"></i> Logo &amp; contact info</a>
        <a href="settings.php?tab=homepage"><i class="fa-solid fa-table-cells-large"></i> Homepage sections</a>
        <a href="settings.php?tab=theme"><i class="fa-solid fa-palette"></i> Brand colors</a>
        <a href="settings.php?tab=seo"><i class="fa-solid fa-magnifying-glass"></i> SEO &amp; analytics</a>
        <a href="settings.php?tab=ceo"><i class="fa-solid fa-user-tie"></i> CEO message</a>
      </div>
    </div>
    <?php if ($popular): ?>
    <div class="card">
      <div class="card-head"><h3>Most requested packages</h3></div>
      <ul class="plain-list">
        <?php foreach ($popular as $p): ?><li><span><?= e($p['package_name']) ?></span><strong><?= (int) $p['n'] ?></strong></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
admin_footer();
