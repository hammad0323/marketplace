<?php
/** CMS page: /about-us/ or /page/{slug}/ */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$page = db_row('SELECT * FROM pages WHERE slug = ? AND is_active = 1', [$GLOBALS['ROUTE_PARAMS']['slug']]);
if (!$page) route_404();
if (($GLOBALS['ROUTE'][0] ?? '') === 'page') redirect($page['slug'] . '/', 301);
$faqs = db_all('SELECT * FROM page_faqs WHERE page_id = ? ORDER BY sort_order, id', [$page['id']]);
$crumbs = [['Home', url('')], [$page['title'], null]];
seo_apply(seo_get('page', (int) $page['id']), ['title' => $page['title'], 'description' => str_limit(strip_tags((string) $page['content']), 160), 'canonical' => abs_url($page['slug'] . '/')]);
page_set(['breadcrumbs' => $crumbs]);
if ($faqs) page_add_schema(schema_faq($faqs)); // only genuine, visible FAQs
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head"><?= breadcrumbs_html($crumbs) ?><h1><?= e($page['title']) ?></h1></div>
  <div class="box cms-content">
    <?= safe_html($page['content']) ?>
    <?php if ($faqs): ?>
    <div class="accordion mt-3" id="faqAcc">
      <?php foreach ($faqs as $i => $fq): ?>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button<?= $i ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= (int) $fq['id'] ?>"><?= e($fq['question']) ?></button></h2>
        <div id="faq<?= (int) $fq['id'] ?>" class="accordion-collapse collapse<?= $i ? '' : ' show' ?>" data-bs-parent="#faqAcc"><div class="accordion-body"><?= nl2br(e($fq['answer'])) ?></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require FS_ROOT . '/partials/footer.php';
