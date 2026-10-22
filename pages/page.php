<?php
/** CMS content pages (about, policies, guides, FAQs). Contact uses its own template. */
$pageRow = page_by_slug($ROUTE['params']['slug'], (bool) current_admin());
if (!$pageRow) {
    route_not_found($ROUTE['path']);
}
if ($pageRow['template'] === 'contact') {
    require UC_ROOT . '/pages/contact.php';
    return;
}
$crumbs = [['Home', url()], [$pageRow['title'], url($pageRow['slug'])]];
seo_set([
    'title' => $pageRow['seo_title'] ?: $pageRow['title'],
    'description' => $pageRow['meta_description'] ?: $pageRow['excerpt'],
    'image' => $pageRow['banner_image'],
    'noindex' => (bool) $pageRow['noindex'] || $pageRow['status'] !== 'published',
    'breadcrumbs' => $crumbs,
]);
$content = sanitize_html($pageRow['content']);
if ($pageRow['template'] === 'faq') {
    // Turn <h3>Question</h3><p>Answer</p> pairs into an accordion + FAQPage schema
    preg_match_all('#<h3>(.*?)</h3>(.*?)(?=<h3>|$)#s', $content, $m, PREG_SET_ORDER);
    if ($m) {
        seo_add_schema(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($q) => [
            '@type' => 'Question', 'name' => trim(strip_tags($q[1])), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim(strip_tags($q[2]))],
        ], $m)]);
        $content = '<div class="uc-accordion">' . implode('', array_map(fn($q) => '<details><summary>' . $q[1] . '</summary><div class="uc-rich">' . $q[2] . '</div></details>', $m)) . '</div>';
    }
}
$bodyClass = 'page-cms';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-listing-hero<?= $pageRow['banner_image'] ? ' has-banner' : '' ?>">
    <?php if ($pageRow['banner_image']): ?><div class="uc-listing-banner" data-parallax="0.2"><img src="<?= e(img_sized($pageRow['banner_image'], 1920)) ?>" alt="<?= e($pageRow['title']) ?>"></div><?php endif; ?>
    <div class="uc-container uc-listing-hero-inner">
        <?= breadcrumbs_html($crumbs) ?>
        <h1 class="uc-page-title"><?= e($pageRow['title']) ?></h1>
        <?php if ($pageRow['excerpt']): ?><p class="uc-page-intro"><?= e($pageRow['excerpt']) ?></p><?php endif; ?>
    </div>
</section>
<article class="uc-container uc-page-pad <?= $pageRow['template'] === 'wide' ? 'uc-content-wide' : 'uc-content' ?>">
    <div class="uc-rich" data-reveal="fade-up"><?= $content ?></div>
    <?php if ($pageRow['slug'] !== 'contact'): ?>
        <aside class="uc-content-help"><p>Still have questions?</p><a class="uc-btn uc-btn--outline" href="<?= e(url('contact')) ?>">Contact us</a></aside>
    <?php endif; ?>
</article>
<?php require UC_ROOT . '/includes/footer.php';
