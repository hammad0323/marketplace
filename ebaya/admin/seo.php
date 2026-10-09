<?php
/** SEO settings, analytics, robots/ads.txt, redirects, sitemap overview. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('seo.manage');
if (is_post()) {
    csrf_check();
    $act = post('action');
    if ($act === 'settings') {
        settings_group_save('seo');
        audit('seo_settings', 'settings');
        flash('success', 'SEO settings saved.');
    } elseif ($act === 'redirect_save') {
        $from = '/' . trim(parse_url(post('from_path'), PHP_URL_PATH) ?? '', '/');
        $to = trim(post('to_path'));
        if ($from === '/' || !v_url($to) || $to === $from) flash('danger', 'Enter an old path (e.g. /old-page) and a valid destination (/new-page or https://…).');
        else {
            db_exec('INSERT INTO redirects (from_path, to_path, status_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), status_code = VALUES(status_code), is_auto = 0',
                [mb_substr($from, 0, 255), mb_substr($to, 0, 255), post('status_code') === '302' ? 302 : 301]);
            audit('redirect_save', 'redirect', null, ['from' => $from, 'to' => $to]);
            flash('success', 'Redirect saved.');
        }
    } elseif ($act === 'redirect_delete') {
        db_exec('DELETE FROM redirects WHERE id = ?', [(int)post('id')]);
        flash('success', 'Redirect removed.');
    }
    redirect(admin_url('seo'));
}
$redirects = db_all('SELECT * FROM redirects ORDER BY id DESC LIMIT 300');
$missing = [
    'Products without meta description' => (int)db_val("SELECT COUNT(*) FROM products WHERE status='published' AND (meta_description IS NULL OR meta_description = '') AND (short_description IS NULL OR short_description = '')"),
    'Product images without alt text' => (int)db_val("SELECT COUNT(*) FROM product_images WHERE alt_text IS NULL OR alt_text = ''"),
    'Categories without meta description' => (int)db_val("SELECT COUNT(*) FROM categories WHERE status='active' AND (meta_description IS NULL OR meta_description = '') AND (description IS NULL OR description = '')"),
];
$counts = ['Products' => (int)db_val("SELECT COUNT(*) FROM products WHERE status='published' AND noindex=0"), 'Categories' => (int)db_val("SELECT COUNT(*) FROM categories WHERE status='active' AND noindex=0"),
           'Collections' => (int)db_val("SELECT COUNT(*) FROM collections WHERE status='active' AND noindex=0"), 'Pages' => (int)db_val("SELECT COUNT(*) FROM pages WHERE status='published' AND noindex=0")];
$admin_title = 'SEO & redirects';
require __DIR__ . '/partials/header.php';
?>
<div class="row g-3">
  <div class="col-xl-7"><div class="card"><div class="card-header">SEO, verification & analytics</div><div class="card-body">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="settings"><?= settings_group_form('seo') ?><button class="btn btn-primary">Save</button></form>
  </div></div></div>
  <div class="col-xl-5">
    <div class="card mb-3"><div class="card-header">Sitemap & robots</div><div class="card-body small">
      <p>XML sitemap (generated live from published, indexable content): <a href="<?= e(url('sitemap.xml')) ?>" target="_blank"><?= e(abs_url('sitemap.xml')) ?></a></p>
      <ul><?php foreach ($counts as $k => $v): ?><li><?= e($k) ?>: <?= $v ?></li><?php endforeach; ?></ul>
      <p>robots.txt: <a href="<?= e(url('robots.txt')) ?>" target="_blank">view</a> · ads.txt: <a href="<?= e(url('ads.txt')) ?>" target="_blank">view</a></p>
      <p class="mb-0">Submit the sitemap URL in Google Search Console after adding your verification code.</p>
    </div></div>
    <div class="card mb-3"><div class="card-header">Content health</div><ul class="list-group list-group-flush"><?php foreach ($missing as $k => $v): ?><li class="list-group-item d-flex small"><?= e($k) ?><span class="ms-auto badge <?= $v ? 'text-bg-warning' : 'text-bg-success' ?>"><?= $v ?></span></li><?php endforeach; ?></ul></div>
    <div class="card"><div class="card-header">Redirects</div><div class="card-body">
      <form method="post" class="row g-1 mb-3"><?= csrf_field() ?><input type="hidden" name="action" value="redirect_save">
        <div class="col-5"><input class="form-control form-control-sm" name="from_path" placeholder="/old-url" required></div>
        <div class="col-5"><input class="form-control form-control-sm" name="to_path" placeholder="/new-url" required></div>
        <div class="col-2"><select class="form-select form-select-sm" name="status_code"><option>301</option><option>302</option></select></div>
        <div class="col-12"><button class="btn btn-sm btn-primary mt-1">Add redirect</button> <span class="small text-muted">Slug changes on products, categories, collections and pages add redirects automatically.</span></div></form>
      <div style="max-height:420px;overflow:auto"><table class="table table-sm mb-0"><tbody>
        <?php foreach ($redirects as $r): ?><tr><td class="small"><?= e($r['from_path']) ?> → <?= e($r['to_path']) ?><div class="text-muted"><?= (int)$r['status_code'] ?> · <?= (int)$r['hits'] ?> hits<?= $r['is_auto'] ? ' · auto' : '' ?></div></td>
          <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="redirect_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-x"></i></button></form></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/partials/footer.php';
