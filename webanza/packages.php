<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$page_title = 'Packages & Pricing';
$page_desc  = setting('packages_subheading');
require __DIR__ . '/partials/header.php';

page_hero('Packages & *Pricing*', setting('packages_subheading'), ['Packages' => ''], 'Pricing');
section_packages(false);
section_stats();
section_testimonials();
section_faq();
section_cta();

require __DIR__ . '/partials/footer.php';
