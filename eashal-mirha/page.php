<?php
require __DIR__ . '/includes/bootstrap.php';

$page = row('SELECT * FROM pages WHERE slug = ? AND status = 1', [get('slug')]);
if (!$page) not_found();

$seo = [
    'title'       => $page['meta_title'] ?: $page['title'] . ' | ' . setting('site_name'),
    'description' => $page['meta_description'] ?: excerpt($page['content'], 158),
    'canonical'   => abs_url('page/' . $page['slug']),
];
require ROOT . '/includes/header.php';
?>
<section class="page-title"><div class="container"><span class="ornament">✦</span><h1 class="section-title"><?= e($page['title']) ?></h1></div></section>
<section class="section section--tight">
  <div class="container narrow rich page-content" data-reveal><?= $page['content'] ?></div>
</section>
<?php require ROOT . '/includes/footer.php';
