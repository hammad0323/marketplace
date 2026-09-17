<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$page = wh_fetch_one("SELECT * FROM pages WHERE business_id=? AND page_key='terms'", 'i', [$businessId]);

$pageTitle = $page['title'] ?? 'Terms & Conditions';
$activeNav = '';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container"><h1><?= e($page['title'] ?? 'Terms & Conditions') ?></h1></div>
</section>
<section class="section">
  <div class="container reveal" style="max-width:820px;"><?= $page['content'] ?? '<p>Content coming soon.</p>' ?></div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
