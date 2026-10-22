<?php
require __DIR__ . '/../includes/init.php';
admin_require('seo');
if (is_post()) {
    $text = ['seo_site_title' => 190, 'seo_tagline' => 190, 'seo_title_template' => 120, 'seo_default_description' => 320, 'seo_google_verification' => 120,
             'seo_bing_verification' => 120, 'seo_facebook_domain_verification' => 120, 'analytics_ga4_id' => 30, 'analytics_gtm_id' => 30, 'analytics_fb_pixel' => 30];
    foreach ($text as $k => $max) {
        setting_save($k, mb_substr(strip_tags(input($k, '', 'post')), 0, $max), 'seo');
    }
    foreach (['robots_txt', 'ads_txt'] as $k) {
        setting_save($k, mb_substr(str_replace("\r", '', (string) ($_POST[$k] ?? '')), 0, 20000), 'seo');
    }
    setting_save('seo_noindex_site', (string) input_bool('seo_noindex_site'), 'seo');
    setting_save('analytics_exclude_admins', (string) input_bool('analytics_exclude_admins'), 'seo');
    $og = a_try(fn() => a_image_value('seo_og_image', setting('seo_og_image')), setting('seo_og_image'));
    setting_save('seo_og_image', (string) $og, 'seo');
    foreach (['analytics_ga4_id' => '/^(G-[A-Z0-9]{4,15})?$/', 'analytics_gtm_id' => '/^(GTM-[A-Z0-9]{4,12})?$/', 'analytics_fb_pixel' => '/^(\d{6,20})?$/'] as $k => $re) {
        if (!preg_match($re, setting($k, ''))) flash('warning', ucfirst(str_replace('_', ' ', $k)) . ' looks invalid and will not be output.');
    }
    audit_log('seo_settings', 'settings');
    flash('success', 'SEO settings saved.');
    redirect('admin/seo/');
}
admin_header('SEO & analytics');
?>
<ul class="nav a-tabs"><li class="nav-item"><a class="nav-link active" href="#">Settings</a></li><li class="nav-item"><a class="nav-link" href="<?= e(url('admin/seo/redirects.php')) ?>">Redirects</a></li></ul>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-4"><div class="col-lg-7">
<div class="card a-card"><div class="card-header">Search appearance</div><div class="card-body">
    <?= a_input('seo_site_title', 'Site title', setting('seo_site_title', store_name())) ?>
    <?= a_input('seo_tagline', 'Homepage tagline (title suffix on the homepage)', setting('seo_tagline')) ?>
    <?= a_input('seo_title_template', 'Page title template', setting('seo_title_template', '{title} | {site}'), 'text', [], 'Use {title} and {site}.') ?>
    <?= a_textarea('seo_default_description', 'Default meta description', setting('seo_default_description'), 3) ?>
    <?= a_image('seo_og_image', 'Default social share image (1200×630)', setting('seo_og_image')) ?>
    <?= a_check('seo_noindex_site', 'Discourage search engines from indexing the whole site (use while building)', setting_bool('seo_noindex_site')) ?>
</div></div>
<div class="card a-card"><div class="card-header">robots.txt</div><div class="card-body">
    <?= a_textarea('robots_txt', 'Content', setting('robots_txt'), 9, ['class' => 'font-monospace'], 'The sitemap line is added automatically: ' . e(url('sitemap.xml'))) ?>
</div></div>
<div class="card a-card"><div class="card-header">ads.txt</div><div class="card-body">
    <?= a_textarea('ads_txt', 'Content (leave empty to return 404)', setting('ads_txt'), 4) ?>
</div></div>
</div><div class="col-lg-5">
<div class="card a-card"><div class="card-header">Verification</div><div class="card-body">
    <?= a_input('seo_google_verification', 'Google Search Console (content value only)', setting('seo_google_verification')) ?>
    <?= a_input('seo_bing_verification', 'Bing Webmaster', setting('seo_bing_verification')) ?>
    <?= a_input('seo_facebook_domain_verification', 'Facebook domain verification', setting('seo_facebook_domain_verification')) ?>
</div></div>
<div class="card a-card"><div class="card-header">Analytics</div><div class="card-body">
    <?= a_input('analytics_ga4_id', 'Google Analytics 4 measurement ID', setting('analytics_ga4_id'), 'text', ['placeholder' => 'G-XXXXXXXXXX']) ?>
    <?= a_input('analytics_gtm_id', 'Google Tag Manager ID', setting('analytics_gtm_id'), 'text', ['placeholder' => 'GTM-XXXXXXX']) ?>
    <?= a_input('analytics_fb_pixel', 'Meta (Facebook) Pixel ID', setting('analytics_fb_pixel')) ?>
    <?= a_check('analytics_exclude_admins', 'Do not track signed-in admins', setting_bool('analytics_exclude_admins', true)) ?>
</div></div>
<div class="card a-card"><div class="card-header">Sitemap</div><div class="card-body small">
    Generated live from published, indexable products, categories, collections and pages:<br><a href="<?= e(url('sitemap.xml')) ?>" target="_blank"><?= e(url('sitemap.xml')) ?></a><br>Submit it in Google Search Console.
</div></div>
</div></div>
<?= a_save_bar() ?>
</form>
<?php admin_footer();
