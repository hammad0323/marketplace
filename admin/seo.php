<?php
require __DIR__ . '/includes/init.php';
admin_require('seo');
$statics = ['home' => 'Homepage', 'shop' => 'Shop (all products)', 'monthly-rashan' => 'Monthly Rashan page', 'offers' => 'Offers page', 'brands' => 'Brands page', 'contact' => 'Contact page'];
$tab = input('tab', 'general');
if (is_post()) {
    csrf_check();
    $act = input('act');
    if ($act === 'general') {
        foreach (['site_name', 'site_tagline', 'meta_description', 'google_verification', 'twitter_handle', 'robots_extra', 'opening_hours_schema'] as $k) setting_set($k, input($k));
        flash('success', 'Global SEO saved.');
    } elseif ($act === 'static' && isset($statics[input('key')])) {
        seo_save('static', 0, input_arr('seo'), input('key'));
        flash('success', 'SEO saved for ' . $statics[input('key')] . '.');
    } elseif ($act === 'redirect') {
        $old = '/' . trim(parse_url(input('old_path'), PHP_URL_PATH) ?: '', '/') . '/';
        $new = input('new_path');
        if ($old === '//' || $new === '') flash('danger', 'Both paths are required.');
        else { db_exec('INSERT INTO redirects (old_path, new_path, status_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE new_path = VALUES(new_path), status_code = VALUES(status_code)', [$old, '/' . ltrim($new, '/'), in_array(input_int('status_code'), [301, 302], true) ? input_int('status_code') : 301]); flash('success', 'Redirect saved.'); }
    } elseif ($act === 'del_redirect') {
        db_exec('DELETE FROM redirects WHERE id = ?', [input_int('id')]);
    } elseif ($act === 'synonym') {
        $t = mb_strtolower(trim(input('term')));
        if ($t !== '' && input('synonyms') !== '') db_exec('INSERT INTO search_synonyms (term, synonyms) VALUES (?, ?) ON DUPLICATE KEY UPDATE synonyms = VALUES(synonyms)', [$t, mb_strtolower(input('synonyms'))]);
        setting_set('popular_searches', input('popular_searches'));
        cache_clear('popular_searches');
        flash('success', 'Search settings saved.');
    } elseif ($act === 'del_synonym') {
        db_exec('DELETE FROM search_synonyms WHERE id = ?', [input_int('id')]);
    } elseif ($act === 'reindex') {
        foreach (db_col('SELECT id FROM products WHERE deleted_at IS NULL') as $pid) product_rebuild_search((int) $pid);
        catalog_changed();
        flash('success', 'Search index rebuilt for all products.');
    }
    redirect('admin/seo.php?tab=' . $tab . (input('key') ? '&key=' . urlencode(input('key')) : ''));
}
// SEO health checks
$missingMeta = (int) db_val("SELECT COUNT(*) FROM products p WHERE p.deleted_at IS NULL AND p.status = 'active' AND NOT EXISTS (SELECT 1 FROM seo_metadata s WHERE s.entity_type = 'product' AND s.entity_id = p.id AND s.meta_description IS NOT NULL) AND (p.short_description IS NULL OR p.short_description = '')");
$noImage = (int) db_val("SELECT COUNT(*) FROM products p WHERE p.deleted_at IS NULL AND p.status = 'active' AND NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)");
$noAlt = (int) db_val("SELECT COUNT(*) FROM product_images WHERE alt_text IS NULL OR alt_text = ''");
$catNoDesc = (int) db_val("SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL AND is_active = 1 AND (description IS NULL OR description = '')");
$zeroSearches = db_all("SELECT term, COUNT(*) c FROM search_logs WHERE results_count = 0 AND created_at > ? GROUP BY term ORDER BY c DESC LIMIT 15", [date('Y-m-d', strtotime('-30 days'))]);
$topSearches = db_all("SELECT term, COUNT(*) c FROM search_logs WHERE created_at > ? GROUP BY term ORDER BY c DESC LIMIT 15", [date('Y-m-d', strtotime('-30 days'))]);
admin_header('SEO Management', 'seo');
$tabs = ['general' => 'Global SEO', 'pages' => 'Static pages', 'redirects' => '301 Redirects', 'search' => 'Search & synonyms', 'health' => 'SEO health'];
?>
<ul class="nav nav-tabs mb-3"><?php foreach ($tabs as $k => $l): ?><li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?tab=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
<?php if ($tab === 'general'): ?>
<form method="post" class="card" style="max-width:820px"><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="general">
  <?= af_text('site_name', 'Site name', setting('site_name')) ?><?= af_text('site_tagline', 'Tagline (default title suffix)', setting('site_tagline')) ?>
  <?= af_textarea('meta_description', 'Default meta description', setting('meta_description'), ['rows' => 2, 'class' => 'form-control js-count', 'data-max' => 160]) ?>
  <div class="row"><div class="col-md-6"><?= af_text('google_verification', 'Google Search Console verification code', setting('google_verification')) ?></div><div class="col-md-6"><?= af_text('twitter_handle', 'Twitter/X handle', setting('twitter_handle'), ['placeholder' => '@fahimstore']) ?></div></div>
  <?= af_text('opening_hours_schema', 'Opening hours (LocalBusiness schema)', setting('opening_hours_schema', 'Mo-Su 09:00-22:00')) ?>
  <?= af_textarea('robots_extra', 'Extra robots.txt rules', setting('robots_extra'), ['rows' => 3, 'class' => 'form-control font-monospace small']) ?>
  <p class="small">Sitemap: <a href="<?= url('sitemap.xml') ?>" target="_blank"><?= e(abs_url('sitemap.xml')) ?></a> (generated live) · <a href="<?= url('robots.txt') ?>" target="_blank">robots.txt</a></p>
  <p class="small text-muted mb-0">Product, category, brand, promotion and page SEO fields are on each item's edit screen. Organization, WebSite + SearchAction, LocalBusiness, Product/Offer, BreadcrumbList and FAQ schema are generated automatically.</p>
</div><div class="card-footer bg-white"><button class="btn btn-brand">Save</button></div></form>
<?php elseif ($tab === 'pages'): $key = isset($statics[input('key')]) ? input('key') : 'home'; ?>
<div class="row g-3"><div class="col-md-3"><div class="list-group"><?php foreach ($statics as $k => $l): ?><a href="?tab=pages&key=<?= $k ?>" class="list-group-item list-group-item-action <?= $key === $k ? 'active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div></div>
<div class="col-md-9"><form method="post" class="card"><div class="card-header"><?= e($statics[$key]) ?></div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="static"><input type="hidden" name="key" value="<?= e($key) ?>">
  <?= af_seo_fields(seo_get('static', 0, $key), $key === 'home' ? '' : $key . '/', '') ?></div><div class="card-footer bg-white"><button class="btn btn-brand">Save</button></div></form></div></div>
<?php elseif ($tab === 'redirects'): $rows = db_all('SELECT * FROM redirects ORDER BY id DESC LIMIT 300'); ?>
<form method="post" class="card mb-3"><div class="card-body row g-2 align-items-end"><?= csrf_field() ?><input type="hidden" name="act" value="redirect">
  <div class="col-md-5"><label class="form-label small" for="op">Old path</label><input id="op" name="old_path" class="form-control" placeholder="/old-product-url/" required></div>
  <div class="col-md-4"><label class="form-label small" for="np">New path or URL</label><input id="np" name="new_path" class="form-control" placeholder="/product/new-url/" required></div>
  <div class="col-md-1"><select name="status_code" class="form-select" aria-label="Type"><option>301</option><option>302</option></select></div>
  <div class="col-md-2"><button class="btn btn-brand w-100">Add</button></div></div></form>
<p class="small text-muted">Redirects are created automatically when you change a product, category, brand, promotion or page URL.</p>
<div class="card"><table class="table table-sm mb-0"><thead><tr><th>Old</th><th>New</th><th>Code</th><th>Hits</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="small"><?= e($r['old_path']) ?></td><td class="small"><?= e($r['new_path']) ?></td><td><?= (int) $r['status_code'] ?></td><td><?= (int) $r['hits'] ?></td><td class="text-end"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="del_redirect"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0">delete</button></form></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="text-muted p-3">No redirects yet.</td></tr><?php endif; ?></tbody></table></div>
<?php elseif ($tab === 'search'): $syn = db_all('SELECT * FROM search_synonyms ORDER BY term'); ?>
<div class="row g-3"><div class="col-lg-7"><form method="post" class="card mb-3"><div class="card-header">Search synonyms <small class="text-muted fw-normal">(e.g. atta → flour, wheat flour)</small></div><div class="card-body"><?= csrf_field() ?><input type="hidden" name="act" value="synonym">
  <div class="row g-2"><div class="col-md-4"><input name="term" class="form-control" placeholder="Customer types: atta" aria-label="Term"></div><div class="col-md-8"><input name="synonyms" class="form-control" placeholder="Also search: flour, wheat flour" aria-label="Synonyms"></div></div>
  <?= af_text('popular_searches', 'Popular searches shown in the search box (comma separated)', setting('popular_searches'), ['class' => 'form-control mt-3']) ?>
  <button class="btn btn-brand">Save</button></div></form>
  <div class="card"><table class="table table-sm mb-0"><tbody><?php foreach ($syn as $s): ?><tr><td><strong><?= e($s['term']) ?></strong></td><td class="small"><?= e($s['synonyms']) ?></td><td class="text-end"><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="del_synonym"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0">delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="col-lg-5"><div class="card mb-3"><div class="card-header">Searches with no results (30 days)</div><ul class="list-group list-group-flush small"><?php foreach ($zeroSearches as $z): ?><li class="list-group-item d-flex justify-content-between"><?= e($z['term']) ?><span class="badge bg-danger"><?= (int) $z['c'] ?></span></li><?php endforeach; if (!$zeroSearches): ?><li class="list-group-item text-muted">None 🎉</li><?php endif; ?></ul></div>
  <div class="card mb-3"><div class="card-header">Top searches (30 days)</div><ul class="list-group list-group-flush small"><?php foreach ($topSearches as $z): ?><li class="list-group-item d-flex justify-content-between"><?= e($z['term']) ?><span class="badge bg-light text-dark border"><?= (int) $z['c'] ?></span></li><?php endforeach; ?></ul></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="reindex"><button class="btn btn-outline-secondary w-100">Rebuild search index</button></form></div></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ([['Active products without a description/meta', $missingMeta, 'products.php'], ['Active products without images', $noImage, 'products.php?flag=noimage'], ['Product images without ALT text', $noAlt, 'products.php'], ['Active categories without description', $catNoDesc, 'categories.php']] as [$l, $n, $link]): ?>
  <div class="col-md-6 col-xl-3"><a href="<?= aurl($link) ?>" class="kpi text-decoration-none text-dark"><i class="bi <?= $n ? 'bi-exclamation-circle text-warning' : 'bi-check-circle text-success' ?>"></i><div><small><?= e($l) ?></small><strong><?= $n ?></strong></div></a></div>
  <?php endforeach; ?>
</div>
<div class="card mt-3"><div class="card-body small"><strong>Built-in technical SEO:</strong> clean URLs without .php, dynamic XML sitemap with images, robots.txt, canonical tags, SEO-friendly pagination with rel prev/next, automatic 301 redirects on URL changes, breadcrumbs + BreadcrumbList schema, Product/Offer/AggregateRating schema, Organization, WebSite + SearchAction and LocalBusiness schema, FAQ schema only for visible FAQs, lazy-loaded WebP images, noindex on admin, cart, checkout, account and search pages.</div></div>
<?php endif;
admin_footer();
