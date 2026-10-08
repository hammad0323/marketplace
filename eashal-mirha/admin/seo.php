<?php
require __DIR__ . '/includes/admin.php';
require_section('seo');

if (is_post()) {
    require_csrf();
    switch (post('do')) {
        case 'meta':
            save_posted_settings(['meta_title', 'meta_description', 'meta_keywords', 'google_verification', 'ga_id', 'fb_pixel', 'head_code', 'body_code']);
            $og = handle_image('og_image', setting('og_image') ?: null, 'settings');
            save_settings(['og_image' => (string)$og]);
            flash('success', 'SEO settings saved.');
            break;
        case 'robots':
            save_settings(['robots_txt' => post('robots_txt')]);
            $ok = @file_put_contents(ROOT . '/robots.txt', robots_txt()) !== false;
            flash($ok ? 'success' : 'info', $ok ? 'robots.txt saved.' : 'Saved — robots.txt is served dynamically (folder not writable).');
            break;
        case 'robots_reset':
            save_settings(['robots_txt' => '']);
            @file_put_contents(ROOT . '/robots.txt', robots_txt());
            flash('success', 'robots.txt reset to default.');
            break;
        case 'ads':
            save_settings(['ads_txt' => post('ads_txt')]);
            $ok = @file_put_contents(ROOT . '/ads.txt', trim(post('ads_txt')) . "\n") !== false;
            flash($ok ? 'success' : 'info', $ok ? 'ads.txt saved.' : 'Saved — ads.txt is served dynamically (folder not writable).');
            break;
        case 'sitemap':
            $res = write_seo_files();
            save_settings(['sitemap_generated' => date('Y-m-d H:i:s')]);
            flash($res['sitemap.xml'] ? 'success' : 'info', $res['sitemap.xml'] ? 'Sitemap generated with ' . substr_count(sitemap_xml(), '<url>') . ' URLs.' : 'Sitemap is served dynamically (folder not writable).');
            break;
    }
    redirect('admin/seo');
}
$s = fn($k, $d = '') => setting($k, $d);
admin_header('SEO Tools', 'seo');
?>
<div class="grid-2">
  <form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?><input type="hidden" name="do" value="meta">
    <div class="card__head"><h3>Homepage & default meta tags</h3></div>
    <div class="serp">
      <span class="serp__url"><?= e(site_url()) ?></span>
      <span class="serp__title" data-serp-title><?= e($s('meta_title')) ?></span>
      <span class="serp__desc" data-serp-desc><?= e($s('meta_description')) ?></span>
    </div>
    <?= f_text('meta_title', 'Meta title', $s('meta_title'), ['attrs' => 'data-count="60" data-serp="title"']) ?>
    <?= f_text('meta_description', 'Meta description', $s('meta_description'), ['type' => 'textarea', 'rows' => 3, 'attrs' => 'data-count="160" data-serp="desc"']) ?>
    <?= f_text('meta_keywords', 'Meta keywords', $s('meta_keywords')) ?>
    <?= f_image('og_image', 'Default social share image', $s('og_image') ?: null, '1200×630 — used when a page has no image of its own') ?>
    <h4 class="mt">Tracking & verification</h4>
    <div class="row-3">
      <?= f_text('google_verification', 'Google Search Console code', $s('google_verification'), ['help' => 'Only the content="…" value']) ?>
      <?= f_text('ga_id', 'Google Analytics ID', $s('ga_id'), ['help' => 'e.g. G-XXXXXXX']) ?>
      <?= f_text('fb_pixel', 'Meta (Facebook) Pixel ID', $s('fb_pixel')) ?>
    </div>
    <?= f_text('head_code', 'Custom code in &lt;head&gt;', $s('head_code'), ['type' => 'textarea', 'rows' => 3, 'attrs' => 'class="mono"', 'help' => 'e.g. TikTok pixel, other verification tags']) ?>
    <?= f_text('body_code', 'Custom code after &lt;body&gt;', $s('body_code'), ['type' => 'textarea', 'rows' => 3, 'attrs' => 'class="mono"']) ?>
    <button class="btn btn-primary">Save SEO Settings</button>
  </form>

  <div>
    <div class="card">
      <div class="card__head"><h3>XML Sitemap</h3><a class="link sm" target="_blank" href="<?= url('sitemap.xml') ?>">Open ↗</a></div>
      <p>Your sitemap lists the homepage, all active categories, products and pages. Submit <code><?= e(abs_url('sitemap.xml')) ?></code> to Google Search Console.</p>
      <p class="muted sm">Last generated: <?= e($s('sitemap_generated', 'never')) ?> · Live URLs now: <?= substr_count(sitemap_xml(), '<url>') ?></p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="sitemap"><button class="btn btn-primary">Generate Sitemap Now</button></form>
    </div>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="do" value="robots">
      <div class="card__head"><h3>robots.txt</h3><a class="link sm" target="_blank" href="<?= url('robots.txt') ?>">Open ↗</a></div>
      <textarea name="robots_txt" rows="9" class="mono" placeholder="<?= e(default_robots()) ?>"><?= e($s('robots_txt') ?: default_robots()) ?></textarea>
      <div class="btn-row mt"><button class="btn btn-primary">Save robots.txt</button><button class="btn" form="robotsReset">Reset to default</button></div>
    </form>
    <form method="post" id="robotsReset"><?= csrf_field() ?><input type="hidden" name="do" value="robots_reset"></form>
    <form method="post" class="card">
      <?= csrf_field() ?><input type="hidden" name="do" value="ads">
      <div class="card__head"><h3>ads.txt</h3><a class="link sm" target="_blank" href="<?= url('ads.txt') ?>">Open ↗</a></div>
      <textarea name="ads_txt" rows="5" class="mono" placeholder="google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0"><?= e($s('ads_txt')) ?></textarea>
      <small class="help">Paste the line(s) given by Google AdSense or your ad network.</small>
      <button class="btn btn-primary mt">Save ads.txt</button>
    </form>
  </div>
</div>
<?php admin_footer();
