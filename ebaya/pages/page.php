<?php
/** Admin-editable content pages (About, Size Guide, policies, FAQs…). */
if (!defined('EBAYA')) { http_response_code(403); exit; }
if ($page['template'] === 'contact') render_page('contact', ['page' => $page]);
seo_set(['title' => $page['seo_title'] ?: $page['title'], 'description' => $page['meta_description'] ?: str_limit($page['content'], 160),
         'canonical' => abs_url($page['slug']), 'noindex' => (bool)$page['noindex'] || $page['status'] !== 'published', 'image' => $page['banner_image']]);
seo_breadcrumbs([[$page['title'], null]]);
if ($page['template'] === 'faq') {
    // Build FAQPage structured data from <details><summary>Q</summary>A</details> blocks.
    if (preg_match_all('#<details>\s*<summary>(.*?)</summary>(.*?)</details>#si', (string)$page['content'], $m, PREG_SET_ORDER)) {
        seo_schema(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($x) => ['@type' => 'Question', 'name' => trim(strip_tags($x[1])),
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($x[2]))]], $m)]);
    }
}
$bodyClass = 'page-content tpl-' . $page['template'];
require ROOT_PATH . '/templates/header.php';
?>
<section class="listing-hero<?= $page['banner_image'] ? ' has-banner' : '' ?>"<?= $page['banner_image'] ? ' style="--banner:url(' . e(img_url($page['banner_image'])) . ')"' : '' ?>>
  <?php if ($page['banner_image']): ?><div class="listing-hero-bg parallax-bg" aria-hidden="true"></div><?php endif; ?>
  <div class="container-eb">
    <?php include ROOT_PATH . '/templates/breadcrumbs.php'; ?>
    <h1 class="page-title" data-reveal><?= e($page['title']) ?></h1>
    <?php if ($page['subtitle']): ?><p class="page-intro" data-reveal><?= e($page['subtitle']) ?></p><?php endif; ?>
  </div>
</section>
<section class="page-section pt-0">
  <div class="container-eb <?= $page['template'] === 'wide' ? '' : 'narrow' ?>">
    <div class="rich content-body" data-reveal><?= sanitize_html($page['content']) ?></div>
  </div>
</section>
<?php require ROOT_PATH . '/templates/footer.php';
