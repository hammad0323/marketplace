<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('seo.manage');
$errors = [];
$warnings = [];
if (is_post()) {
    require_csrf();
    $section = input('section');
    if ($section === 'meta') {
        foreach (['seo_title_template' => 120, 'seo_default_description' => 320, 'home_seo_title' => 190, 'home_meta_description' => 320, 'shop_seo_title' => 190, 'shop_meta_description' => 320, 'twitter_handle' => 40] as $k => $max) {
            save_setting($k, mb_substr(input($k), 0, $max), 'seo');
        }
        if (strpos(input('seo_title_template'), '{title}') === false) {
            save_setting('seo_title_template', '{title} | {site}', 'seo');
            flash('warning', 'Title template must contain {title}; it was reset to the default.');
        }
        [$og, $err] = handle_image_field('og_default_image', strpos((string) setting('og_default_image'), 'uploads/') === 0 ? setting('og_default_image') : null, 'seo');
        if ($err) {
            $errors[] = $err;
        } elseif ($og !== null) {
            save_setting('og_default_image', $og ?: 'assets/img/brand/og-default.jpg', 'seo');
        }
    } elseif ($section === 'indexing') {
        save_setting('seo_indexing_enabled', input_bool('seo_indexing_enabled') ? '1' : '0', 'seo');
        save_setting('google_site_verification', preg_replace('/[^A-Za-z0-9_\-]/', '', input('google_site_verification')), 'seo');
        save_setting('bing_site_verification', preg_replace('/[^A-Za-z0-9_\-]/', '', input('bing_site_verification')), 'seo');
        $ga = strtoupper(input('ga4_measurement_id'));
        if ($ga !== '' && !preg_match('/^G-[A-Z0-9]{4,15}$/', $ga)) {
            $errors[] = 'GA4 Measurement ID should look like G-XXXXXXXXXX.';
        } else {
            save_setting('ga4_measurement_id', $ga, 'seo');
        }
        if (is_super_admin()) {
            save_setting('head_scripts', mb_substr((string) ($_POST['head_scripts'] ?? ''), 0, 20000), 'seo');
            save_setting('body_scripts', mb_substr((string) ($_POST['body_scripts'] ?? ''), 0, 20000), 'seo');
        }
    } elseif ($section === 'robots') {
        $txt = str_replace("\r", '', mb_substr((string) ($_POST['robots_txt'] ?? ''), 0, 10000));
        $w = robots_txt_warnings($txt ?: default_robots_txt());
        if ($w && !input_bool('confirm_warnings')) {
            $errors = array_merge(['robots.txt was NOT saved:'], $w, ['Tick "I understand" to save anyway.']);
            $_SESSION['_robots_draft'] = $txt;
        } else {
            save_setting('robots_txt', $txt, 'seo');
        }
    } elseif ($section === 'ads') {
        $txt = trim(str_replace("\r", '', mb_substr((string) ($_POST['ads_txt'] ?? ''), 0, 20000)));
        $adsErrors = ads_txt_errors($txt);
        if ($adsErrors) {
            $errors = $adsErrors;
            $_SESSION['_ads_draft'] = $txt;
        } else {
            save_setting('ads_txt', $txt, 'seo');
        }
    }
    if (!$errors) {
        audit_log('seo_updated', 'settings', null, ['section' => $section]);
        flash('success', 'SEO settings saved.');
        redirect(admin_url('seo') . '#' . $section);
    }
}
$robots = $_SESSION['_robots_draft'] ?? setting('robots_txt', '');
$ads = $_SESSION['_ads_draft'] ?? setting('ads_txt', '');
unset($_SESSION['_robots_draft'], $_SESSION['_ads_draft']);
$sitemapCount = count(sitemap_entries());
admin_header('SEO', 'seo');
?>
<?php if ($errors): ?><div class="alert alert-danger"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!setting_bool('seo_indexing_enabled', true)): ?><div class="alert alert-warning"><strong>Indexing is OFF.</strong> All pages send noindex and robots.txt blocks crawling.</div><?php endif; ?>
<div class="row g-3">
  <div class="col-xl-6">
    <div class="card mb-3" id="meta"><div class="card-header">Default metadata</div><div class="card-body">
      <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="section" value="meta">
        <?= f_text('seo_title_template', 'Title template', setting('seo_title_template', '{title} | {site}'), [], 'Use {title} and {site}.') ?>
        <?= f_textarea('seo_default_description', 'Default meta description', setting('seo_default_description'), ['rows' => 2, 'data-count' => 160]) ?>
        <?= f_text('home_seo_title', 'Homepage title (blank = browser title setting)', setting('home_seo_title'), ['data-count' => 60]) ?>
        <?= f_textarea('home_meta_description', 'Homepage meta description', setting('home_meta_description'), ['rows' => 2, 'data-count' => 160]) ?>
        <?= f_text('shop_seo_title', 'Shop page title', setting('shop_seo_title'), ['data-count' => 60]) ?>
        <?= f_textarea('shop_meta_description', 'Shop page meta description', setting('shop_meta_description'), ['rows' => 2, 'data-count' => 160]) ?>
        <?= f_image('og_default_image', 'Default social share image (1200×630)', setting('og_default_image')) ?>
        <?= f_text('twitter_handle', 'X / Twitter handle', setting('twitter_handle'), [], 'e.g. @beglet') ?>
        <?= f_submit() ?>
      </form>
      <p class="small text-muted mt-3 mb-0">Products, categories, collections and pages each have their own SEO title, description and slug. Product, breadcrumb, organisation and website/search structured data are generated automatically.</p>
    </div></div>
    <div class="card mb-3" id="indexing"><div class="card-header">Indexing, verification & analytics</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="section" value="indexing">
        <?= f_check('seo_indexing_enabled', 'Allow search engines to index the site', setting_bool('seo_indexing_enabled', true), 'Turn off on staging sites. Cart, checkout, account, search and filtered listings are always noindex.') ?>
        <?= f_text('google_site_verification', 'Google Search Console verification code', setting('google_site_verification'), [], 'The content value of the google-site-verification meta tag.') ?>
        <?= f_text('bing_site_verification', 'Bing Webmaster verification code', setting('bing_site_verification')) ?>
        <?= f_text('ga4_measurement_id', 'Google Analytics 4 Measurement ID', setting('ga4_measurement_id'), [], 'G-XXXXXXXXXX') ?>
        <?php if (is_super_admin()): ?>
          <?= f_textarea('head_scripts', 'Additional <head> scripts (advertising pixels, tag managers)', setting('head_scripts'), ['rows' => 4, 'style' => 'font-family:monospace;font-size:.8rem'], 'Super Admin only. Output unmodified on every storefront page — paste only code from trusted providers.') ?>
          <?= f_textarea('body_scripts', 'Scripts after <body>', setting('body_scripts'), ['rows' => 3, 'style' => 'font-family:monospace;font-size:.8rem']) ?>
        <?php else: ?><p class="small text-muted">Custom scripts can only be edited by a Super Admin.</p><?php endif; ?>
        <?= f_submit() ?>
      </form>
    </div></div>
  </div>
  <div class="col-xl-6">
    <div class="card mb-3"><div class="card-header">XML sitemap</div><div class="card-body">
      <p class="mb-2"><a href="<?= e(url('sitemap.xml')) ?>" target="_blank"><?= e(url('sitemap.xml')) ?></a> · <?= $sitemapCount ?> URLs</p>
      <p class="small text-muted mb-0">Generated live from published products, active categories, collections and indexable pages, using canonical URLs — it updates automatically whenever content changes. Submit it once in Google Search Console and Bing Webmaster Tools; the robots.txt file also advertises it.</p>
    </div></div>
    <div class="card mb-3" id="robots"><div class="card-header">robots.txt</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="section" value="robots">
        <textarea class="form-control mb-2" name="robots_txt" id="robotsTxt" rows="12" style="font-family:monospace;font-size:.8rem" placeholder="Blank = safe default (shown below)"><?= e($robots) ?></textarea>
        <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_warnings" value="1"> I understand the warnings and want to save anyway</label>
        <div class="d-flex gap-2"><?= f_submit('Save robots.txt') ?><button type="button" class="btn btn-light" onclick="document.getElementById('robotsTxt').value = <?= e(json_encode(default_robots_txt())) ?>">Load safe default</button><a class="btn btn-light" target="_blank" href="<?= e(url('robots.txt')) ?>">View live</a></div>
      </form>
      <details class="mt-3"><summary class="small">Default rules</summary><pre class="small bg-light p-2 mb-0"><?= e(default_robots_txt()) ?></pre></details>
    </div></div>
    <div class="card" id="ads"><div class="card-header">ads.txt</div><div class="card-body">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="section" value="ads">
        <textarea class="form-control mb-2" name="ads_txt" rows="6" style="font-family:monospace;font-size:.8rem" placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"><?= e($ads) ?></textarea>
        <p class="small text-muted">Enter only the lines supplied by your advertising partners (domain, publisher ID, DIRECT/RESELLER, optional certification ID). Published at <a href="<?= e(url('ads.txt')) ?>" target="_blank">/ads.txt</a>; blank returns 404.</p>
        <?= f_submit('Save ads.txt') ?>
      </form>
    </div></div>
  </div>
</div>
<?php admin_footer();
