<?php
require __DIR__ . '/../includes/init.php';
admin_require('content');
$id = input_int('id');
$page = $id ? db_row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
$reserved = ['shop', 'new-arrivals', 'sale', 'search', 'category', 'collections', 'product', 'cart', 'checkout', 'order', 'track-order', 'wishlist', 'account', 'newsletter', 'admin', 'ajax', 'api', 'assets', 'uploads', 'sitemap', 'robots', 'ads'];
$errors = [];
if (is_post()) {
    $d = [
        'title' => mb_substr(input('title', '', 'post'), 0, 190),
        'template' => in_array(input('template', '', 'post'), ['default', 'contact', 'faq', 'wide'], true) ? input('template', '', 'post') : 'default',
        'excerpt' => nullable(mb_substr(input('excerpt', '', 'post'), 0, 320)),
        'content' => sanitize_html($_POST['content'] ?? ''),
        'seo_title' => nullable(mb_substr(input('seo_title', '', 'post'), 0, 190)),
        'meta_description' => nullable(mb_substr(input('meta_description', '', 'post'), 0, 320)),
        'status' => input('status', '', 'post') === 'draft' ? 'draft' : 'published',
        'noindex' => input_bool('noindex'),
    ];
    $slug = slugify(input('slug', '', 'post') ?: $d['title']);
    if (in_array($slug, $reserved, true)) $errors[] = 'That URL is reserved by the store. Please choose another.';
    $d['slug'] = unique_slug('pages', $slug, $id);
    if (mb_strlen($d['title']) < 2) $errors[] = 'Title is required.';
    if (!$errors) {
        $d['banner_image'] = a_try(fn() => a_image_value('banner_image', $page['banner_image'] ?? null), $page['banner_image'] ?? null);
        if ($page) {
            if ($page['slug'] !== $d['slug']) redirect_add('/' . $page['slug'], '/' . $d['slug']);
            db_update('pages', $d, 'id = ?', [$id]);
        } else {
            $id = db_insert('pages', $d);
        }
        audit_log($page ? 'page_updated' : 'page_created', 'page', $id, ['title' => $d['title']]);
        flash('success', 'Page saved.');
        redirect('admin/content/edit.php?id=' . $id);
    }
    $page = array_merge($page ?? [], $d);
}
$p = $page ?? ['template' => 'default', 'status' => 'published'];
admin_header($id ? 'Edit page' : 'Add page', ['editor' => true]);
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="row g-4"><div class="col-lg-8"><div class="card a-card"><div class="card-body">
    <?= a_input('title', 'Title', $p['title'] ?? '', 'text', ['required' => true, 'data-slug-source' => $id ? false : '#f_slug']) ?>
    <?= a_input('slug', 'URL', $p['slug'] ?? '', 'text', ['data-slug-target' => true], 'Page address: /<b>slug</b>') ?>
    <?= a_textarea('excerpt', 'Intro line (shown under the title)', $p['excerpt'] ?? '', 2) ?>
    <?= a_html('content', 'Content', $p['content'] ?? '', 'For the FAQ template, write each question as Heading 3 followed by the answer — it becomes an accordion with FAQ structured data.') ?>
    <?= a_image('banner_image', 'Banner image (optional)', $p['banner_image'] ?? null) ?>
</div></div></div><div class="col-lg-4"><div class="card a-card"><div class="card-body">
    <?= a_select('status', 'Status', ['published' => 'Published', 'draft' => 'Draft (admins only)'], $p['status']) ?>
    <?= a_select('template', 'Template', ['default' => 'Standard', 'wide' => 'Wide editorial', 'faq' => 'FAQ accordion', 'contact' => 'Contact page with form'], $p['template']) ?>
    <?= a_input('seo_title', 'SEO title', $p['seo_title'] ?? '') ?>
    <?= a_textarea('meta_description', 'Meta description', $p['meta_description'] ?? '', 2) ?>
    <?= a_check('noindex', 'Hide from search engines', (int) ($p['noindex'] ?? 0)) ?>
    <button class="btn btn-dark w-100">Save page</button>
    <?php if ($id): ?><a class="btn btn-link w-100" href="<?= e(url($p['slug'])) ?>" target="_blank">View page</a><?php endif; ?>
</div></div></div></div></form>
<?php admin_footer(['editor' => true]);
