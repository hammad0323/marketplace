<?php
/** Homepage — every section is configured in Admin » Homepage. */
$preview = !empty($_GET['preview']) && current_admin() && admin_can('homepage');
$GLOBALS['UC_PREVIEW'] = $preview;
seo_set([
    'title' => null,
    'description' => setting('seo_default_description'),
    'canonical' => url(),
    'is_home' => true,
    'noindex' => $preview,
]);
$sections = homepage_sections($preview);
$headerVariant = ($sections && $sections[0]['section_type'] === 'hero') ? 'transparent' : 'solid';
$bodyClass = 'page-home';
require UC_ROOT . '/includes/header.php';
render_home_sections($preview);
require UC_ROOT . '/includes/footer.php';
