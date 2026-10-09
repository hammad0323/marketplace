<?php
meta_set([
    'title' => setting('home_seo_title', ''),
    'description' => setting('home_meta_description', setting('seo_default_description', '')),
    'canonical' => '',
    'noindex' => homepage_preview_mode(),
]);
if (homepage_preview_mode()) {
    header('Cache-Control: no-store');
}
$sections = homepage_sections();
partial('header');
foreach ($sections as $section) {
    $file = APP_PATH . '/views/sections/' . $section['type'] . '.php';
    if (is_file($file)) {
        view('sections/' . $section['type'], ['s' => $section['s'], 'key' => $section['key']]);
    }
}
partial('footer');
