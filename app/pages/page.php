<?php
$page = $GLOBALS['route']['page'];
meta_set([
    'title' => $page['seo_title'] ?: $page['title'],
    'description' => $page['meta_description'] ?: excerpt($page['content'], 158),
    'canonical' => $page['slug'],
    'noindex' => (bool) $page['noindex'],
    'breadcrumbs' => [['name' => 'Home', 'url' => path_url('/')], ['name' => $page['title'], 'url' => path_url($page['slug'])]],
]);
partial('header');
?>
<section class="page-hero"><div class="container page-hero__inner"><?php partial('breadcrumbs'); ?><h1 class="page-title" data-reveal="fade-up"><?= e($page['title']) ?></h1></div></section>
<div class="container container--narrow page-pad">
  <article class="prose" data-reveal="fade-up"><?= rich_text($page['content']) ?></article>
  <p class="small text-muted mt-5">Last updated <?= e(format_date($page['updated_at'])) ?></p>
</div>
<?php partial('footer');
