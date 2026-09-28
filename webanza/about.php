<?php
require __DIR__ . '/config.php';
require __DIR__ . '/partials/sections.php';

$page_title = 'About Us';
$page_desc  = setting('mission');
require __DIR__ . '/partials/header.php';

page_hero('About ' . setting('site_name', 'Webanza Tech'), setting('site_tagline'), ['About' => ''], 'About');
section_about(true);
section_stats();
section_ceo();
section_team();
section_process();
section_clients();
section_testimonials();
section_cta();

require __DIR__ . '/partials/footer.php';
