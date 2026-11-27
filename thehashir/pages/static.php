<?php
/**
 * Static pages managed in Super Admin → Pages (storage/pages/{slug}.json).
 * Legal pages without a saved version fall back to content/pages/{slug}.html.
 * Preview: /page-preview/{slug} (Super Admin only).
 */
$slug = $ROUTE['slug'];
$preview = !empty($ROUTE['preview']);
if ($preview && !current_admin()) {
    route_page('404');
}
$pg = page_doc($slug);
if (!$pg || (!$preview && $pg['status'] !== 'published')) {
    route_page('404');
}
$seo = isset($pg['seo']) ? $pg['seo'] : array();
$updated = !empty($pg['updated_at']) ? $pg['updated_at'] : '';
page_start(array(
    'title' => !empty($seo['title']) ? $seo['title'] : $pg['title'] . ' | The Hashir',
    'description' => !empty($seo['description']) ? $seo['description'] : excerpt($pg['content'] !== '' ? $pg['content'] : $pg['title'] . ' — The Hashir', 158),
    'canonical' => '/' . $slug,
    'noindex' => $preview || !empty($seo['noindex']),
    'active' => $slug === 'about' ? 'about' : '',
));
if ($preview) {
    echo '<div class="preview-bar"><i class="bi bi-eye" aria-hidden="true"></i> Preview (' . e($pg['status']) . '). <a href="' . e(url(ADMIN_PATH . '/page-edit?slug=' . rawurlencode($slug))) . '">Back to the editor</a></div>';
}
page_hero($pg['title'], $pg['subtitle'] !== '' ? $pg['subtitle'] : ($updated ? 'Last updated ' . fmt_date($updated, 'j F Y') : ''), array(array('Home', '/'), array($pg['title'], null)));
?>
<section class="section-sm">
  <div class="container-xl">
    <div class="legal article">
      <?php if (trim((string)$pg['content']) !== ''): ?>
        <div class="rich"><?= !empty($pg['source']) ? $pg['content'] : safe_rich_html($pg['content']) ?></div>
      <?php endif; ?>
      <?php render_blocks(isset($pg['blocks']) ? $pg['blocks'] : array(), array()); ?>
    </div>
  </div>
</section>
<?php
$extra = '';
if (!empty($GLOBALS['NEEDS_LEAFLET'])) {
    $extra = '<link rel="stylesheet" href="' . e(asset('vendor/leaflet/leaflet.css')) . '"><script src="' . e(asset('vendor/leaflet/leaflet.js')) . '"></script>';
}
page_end($extra);
