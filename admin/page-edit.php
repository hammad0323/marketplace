<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('content.pages');
$id = input_int('id', 0, 'get');
$page = $id ? db_one('SELECT * FROM pages WHERE id = ?', [$id]) : null;
$reserved = ['shop', 'search', 'category', 'collection', 'product', 'cart', 'checkout', 'order', 'track-order', 'wishlist', 'contact', 'newsletter', 'account', 'payment', 'api', 'admin', 'assets', 'uploads', 'app', 'install', 'docs', 'tools', 'storage', 'sitemap-xml', 'robots-txt', 'ads-txt', 'index-php'];
$errors = [];
if (is_post()) {
    require_csrf();
    $d = [
        'title' => mb_substr(input('title'), 0, 190), 'slug' => slugify(input('slug') ?: input('title')),
        'content' => sanitize_html($_POST['content'] ?? ''), 'seo_title' => mb_substr(input('seo_title'), 0, 190) ?: null,
        'meta_description' => mb_substr(input('meta_description'), 0, 320) ?: null,
        'footer_group' => in_array(input('footer_group'), ['none', 'company', 'service', 'policy'], true) ? input('footer_group') : 'none',
        'sort_order' => input_int('sort_order'), 'is_published' => input_bool('is_published'), 'noindex' => input_bool('noindex'),
    ];
    if ($d['title'] === '') {
        $errors[] = 'Title is required.';
    }
    if (in_array($d['slug'], $reserved, true)) {
        $errors[] = 'That URL is reserved by the store. Choose another slug.';
    }
    if (db_val('SELECT COUNT(*) FROM pages WHERE slug = ? AND id <> ?', [$d['slug'], $id])) {
        $errors[] = 'Slug already in use.';
    }
    if (!$errors) {
        if ($page) {
            db_update('pages', $d, 'id = ?', [$id]);
            if ($page['slug'] !== $d['slug']) {
                db_exec('INSERT INTO redirects (source_path, target_path, status_code, is_auto) VALUES (?, ?, 301, 1) ON DUPLICATE KEY UPDATE target_path = VALUES(target_path)', ['/' . $page['slug'], '/' . $d['slug']]);
            }
        } else {
            $id = db_insert('pages', $d);
        }
        audit_log($page ? 'page_updated' : 'page_created', 'page', $id, ['slug' => $d['slug']]);
        flash('success', 'Page saved.');
        redirect(admin_url('page-edit', ['id' => $id]));
    }
    $page = array_merge($page ?? [], $d);
}
$p = $page ?? ['footer_group' => 'none', 'sort_order' => 0, 'is_published' => 1, 'noindex' => 0];
admin_header($id ? 'Edit page' : 'Add page', 'pages');
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-lg-8"><div class="card"><div class="card-body">
    <?= f_text('title', 'Title', $p['title'] ?? '', ['required' => true, 'data-slug-source' => '#f_slug']) ?>
    <?= f_text('slug', 'URL', $p['slug'] ?? '', ['data-slug-target' => true], 'Page is served at /your-slug') ?>
    <?= f_textarea('content', 'Content (HTML)', $p['content'] ?? '', ['rows' => 18, 'style' => 'font-family:monospace;font-size:.85rem'], 'Allowed: headings, paragraphs, lists, links, images, tables, bold/italic. Scripts, styles and inline event handlers are stripped.') ?>
  </div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <?= f_check('is_published', 'Published', (int) $p['is_published']) ?>
    <?= f_check('noindex', 'Hide from search engines (noindex)', (int) $p['noindex']) ?>
    <?= f_select('footer_group', 'Show in footer column', ['none' => 'Not in footer', 'company' => 'Company', 'service' => 'Customer care', 'policy' => 'Policies'], $p['footer_group']) ?>
    <?= f_number('sort_order', 'Order', $p['sort_order'], ['step' => 1]) ?>
    <hr><?= f_text('seo_title', 'SEO title', $p['seo_title'] ?? '', ['data-count' => 60]) ?>
    <?= f_textarea('meta_description', 'Meta description', $p['meta_description'] ?? '', ['rows' => 3, 'data-count' => 160]) ?>
  </div></div></div>
</div>
<div class="sticky-actions"><?= f_submit() ?> <a class="btn btn-light" href="<?= e(admin_url('pages')) ?>">Back</a><?php if ($id): ?> <a class="btn btn-light" target="_blank" href="<?= e(path_url($p['slug'])) ?>">View</a><?php endif; ?></div>
</form>
<?php admin_footer();
