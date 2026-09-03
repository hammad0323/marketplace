<?php
require __DIR__ . '/../../config/config.php';
require_admin();

$type = $_GET['type'] ?? 'homepage';
$entities = [];
if ($type === 'homepage') $entities = [['id' => null, 'name' => 'Homepage']];
if ($type === 'category') $entities = db_fetch_all("SELECT id, name FROM categories ORDER BY name");
if ($type === 'shop') $entities = db_fetch_all("SELECT id, shop_name as name FROM shops ORDER BY shop_name");
if ($type === 'product') $entities = db_fetch_all("SELECT id, name FROM products ORDER BY name LIMIT 100");

foreach ($entities as &$e) {
    $seo = get_seo_meta($type, $e['id']);
    $e['seo_score'] = $seo['seo_score'] ?? 0;
    $e['has_seo'] = (bool)$seo;
}
unset($e);

$dashRole = 'admin'; $pageTitle = 'SEO Management'; $dashUserName = current_admin()['name']; $dashLogoutUrl = admin_url('logout.php');
require __DIR__ . '/../../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>SEO Management</h1></div>
<div class="dash-table-card">
  <h3>Sitemap &amp; Robots</h3>
  <p>Last generated: <?= clean(get_setting('last_sitemap_generated', 'Never')) ?></p>
  <div class="quick-actions">
    <a href="<?= base_url('sitemap.php') ?>" target="_blank" class="qa-btn"><i class="fa-solid fa-arrows-rotate"></i> Generate / View Sitemap</a>
    <a href="<?= base_url('robots.php') ?>" target="_blank" class="qa-btn"><i class="fa-solid fa-robot"></i> View Robots.txt</a>
  </div>
</div>
<div class="filter-tabs">
  <?php foreach (['homepage' => 'Homepage', 'category' => 'Categories', 'shop' => 'Shops', 'product' => 'Products'] as $k => $v): ?>
    <a class="<?= $type === $k ? 'active' : '' ?>" href="<?= admin_url('seo/index.php?type=' . $k) ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div class="dash-table-card">
  <table class="dash-table">
    <thead><tr><th>Name</th><th>SEO Score</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($entities as $e): ?>
      <tr>
        <td><?= clean($e['name']) ?></td>
        <td><div class="seo-score-bar"><div class="seo-score-fill" style="width:<?= $e['seo_score'] ?>%;background:<?= $e['seo_score'] >= 70 ? '#3ccf6c' : ($e['seo_score'] >= 40 ? '#ff7a1a' : '#e0442f') ?>"></div></div> <?= $e['seo_score'] ?>/100</td>
        <td><a href="<?= admin_url('seo/form.php?type=' . $type . '&id=' . $e['id']) ?>" class="btn btn-sm btn-outline">Edit SEO</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../../includes/dashboard-footer.php'; ?>
