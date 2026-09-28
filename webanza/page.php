<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$pg = row('SELECT * FROM pages WHERE slug = ? AND is_active = 1', [$_GET['slug'] ?? '']);
if (!$pg) {
    require __DIR__ . '/404.php';
    exit;
}
$page_title = $pg['title'];
$page_desc  = $pg['subtitle'] ?: excerpt($pg['content'], 155);
require __DIR__ . '/partials/header.php';

page_hero($pg['title'], (string) $pg['subtitle'], [$pg['title'] => '']);
?>
<section class="section">
  <div class="container" style="max-width:860px">
    <div class="prose" data-reveal="up"><?= rich($pg['content']) ?></div>
  </div>
</section>
<?php
require __DIR__ . '/partials/footer.php';
