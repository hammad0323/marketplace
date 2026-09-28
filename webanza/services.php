<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$page_title = 'Our Services';
$page_desc  = setting('services_subheading');
require __DIR__ . '/partials/header.php';

page_hero('Our Services', setting('services_subheading'), ['Services' => ''], 'Services');
section_services(null, false);
section_bands();
section_process();
section_packages();
section_faq();
section_cta();

require __DIR__ . '/partials/footer.php';
