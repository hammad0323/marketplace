<?php
/**
 * static-page.php — generic renderer for CMS static pages (About,
 * Contact, Privacy Policy, custom pages, ...). Root file sets
 * $pageSlugCms then requires this file — same pattern as tool-page.php.
 */

require_once __DIR__ . '/config.php';

$slug = $pageSlugCms ?? '';
$page = get_page_by_slug($slug);

if (!$page) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$seoRow = get_seo_settings('page', (int) $page['id']) ?? [];
$pageTitle = $seoRow['seo_title'] ?? ($page['title'] . ' — ' . tp_setting('site_name'));
$pageDescriptionFallback = $seoRow['meta_description'] ?? tp_setting('site_tagline');
$pageSeo = $seoRow;
$breadcrumbItems = [['label' => 'Home', 'url' => tp_url()], ['label' => $page['title'], 'url' => null]];
require __DIR__ . '/header.php';
?>
<div class="tp-container py-5" style="max-width:840px;">
  <h1 class="fw-bold mb-4"><?= e($page['title']) ?></h1>
  <div class="cms-content">
    <?= $page['content'] /* CKEditor HTML, admin-authored & trusted */ ?>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>
