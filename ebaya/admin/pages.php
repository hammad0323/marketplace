<?php
/** Content & policy pages. */
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin('content.manage');
$errors = [];
$reserved = ['shop', 'category', 'product', 'collections', 'cart', 'checkout', 'account', 'order', 'track-order', 'wishlist', 'search', 'ajax', 'payment', 'admin', 'assets', 'uploads', 'new-arrivals', 'best-sellers', 'newsletter', 'sitemap', 'robots'];
if (is_post()) {
    csrf_check();
    if (post('action') === 'delete') {
        db_exec('DELETE FROM pages WHERE id = ?', [(int)post('id')]);
        audit('page_delete', 'page', (int)post('id'));
        flash('success', 'Page deleted.');
        redirect(admin_url('pages'));
    }
    $pid = (int)post('id');
    $old = $pid ? db_one('SELECT * FROM pages WHERE id = ?', [$pid]) : null;
    $d = [
        'title' => mb_substr(post('title'), 0, 190), 'slug' => slugify(post('slug') ?: post('title')), 'subtitle' => mb_substr(post('subtitle'), 0, 255) ?: null,
        'content' => sanitize_html((string)($_POST['content'] ?? '')), 'template' => in_list(post('template'), ['default', 'contact', 'faq', 'wide'], 'default'),
        'seo_title' => mb_substr(post('seo_title'), 0, 190) ?: null, 'meta_description' => mb_substr(post('meta_description'), 0, 320) ?: null,
        'noindex' => post('noindex') ? 1 : 0, 'status' => post('status') === 'draft' ? 'draft' : 'published',
    ];
    if ($d['title'] === '') $errors[] = 'Title is required.';
    if (in_array($d['slug'], $reserved, true)) $errors[] = 'That URL is reserved by the store.';
    if (db_val('SELECT id FROM pages WHERE slug = ? AND id <> ?', [$d['slug'], $pid])) $errors[] = 'Slug already used.';
    if (!$errors) {
        try {
            $d['banner_image'] = f_image_value('banner_image', $old['banner_image'] ?? null, 'content');
            if ($pid) {
                db_exec('UPDATE pages SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = ?', array_merge(array_values($d), [$pid]));
                if ($old['slug'] !== $d['slug']) redirect_add_auto('/' . $old['slug'], '/' . $d['slug']);
            } else $pid = db_insert('INSERT INTO pages (' . implode(',', array_keys($d)) . ') VALUES (' . db_in($d) . ')', array_values($d));
            audit($old ? 'page_update' : 'page_create', 'page', $pid, ['title' => $d['title']]);
            flash('success', 'Page saved.');
            redirect(admin_url('pages?edit=' . $pid));
        } catch (RuntimeException $e) { $errors[] = $e->getMessage(); }
    }
}
$pages = db_all('SELECT id, title, slug, status, updated_at FROM pages ORDER BY title');
$edit = get('edit') !== '' ? (db_one('SELECT * FROM pages WHERE id = ?', [(int)get('edit')]) ?: ['id' => 0]) : ($errors ? $_POST : null);
$admin_title = 'Content pages';
require __DIR__ . '/partials/header.php';
?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="row g-3"><div class="col-xl-4">
  <div class="d-flex mb-2"><a class="btn btn-sm btn-primary ms-auto" href="?edit=0">Add page</a></div>
  <div class="list-group"><?php foreach ($pages as $p): ?><a class="list-group-item list-group-item-action d-flex<?= (int)($edit['id'] ?? -1) === (int)$p['id'] ? ' active' : '' ?>" href="?edit=<?= (int)$p['id'] ?>"><span><?= e($p['title']) ?><br><small>/<?= e($p['slug']) ?></small></span><span class="ms-auto"><?= status_badge($p['status']) ?></span></a><?php endforeach; ?></div>
</div><div class="col-xl-8"><?php if ($edit !== null): $pv = fn($k, $d = '') => $edit[$k] ?? $d; ?>
  <div class="card"><div class="card-body"><form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$pv('id', 0) ?>">
    <div class="row"><div class="col-md-8"><?= f_text('title', 'Title', $pv('title'), ['required' => true]) ?></div><div class="col-md-4"><?= f_text('slug', 'URL', $pv('slug'), ['help' => 'example.com/<slug>']) ?></div></div>
    <?= f_text('subtitle', 'Subtitle', $pv('subtitle')) ?>
    <?= f_text('content', 'Content (HTML: h2, h3, p, ul, li, strong, em, a, img, table; FAQs: <details><summary>Question</summary><p>Answer</p></details>)', $pv('content'), ['type' => 'textarea', 'rows' => 16]) ?>
    <div class="row"><div class="col-md-4"><?= f_select('template', 'Layout', ['default' => 'Standard', 'wide' => 'Wide', 'faq' => 'FAQ (adds FAQ structured data)', 'contact' => 'Contact (adds the contact form)'], $pv('template', 'default')) ?></div>
      <div class="col-md-4"><?= f_select('status', 'Status', ['published' => 'Published', 'draft' => 'Draft (admins can preview)'], $pv('status', 'published')) ?></div>
      <div class="col-md-4"><?= f_toggle('noindex', 'Hide from search engines', $pv('noindex')) ?></div></div>
    <?= f_image('banner_image', 'Banner image (optional)', $pv('banner_image') ?: null) ?>
    <?= f_text('seo_title', 'SEO title', $pv('seo_title')) ?><?= f_text('meta_description', 'Meta description', $pv('meta_description'), ['type' => 'textarea', 'rows' => 2]) ?>
    <div class="d-flex gap-2"><button class="btn btn-primary">Save page</button><?php if ($pv('id')): ?><a class="btn btn-outline-secondary" href="<?= e(url($pv('slug'))) ?>" target="_blank">View</a><?php endif; ?></div>
  </form>
  <?php if ($pv('id')): ?><form method="post" class="mt-2" data-confirm="Delete this page?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$pv('id') ?>"><button class="btn btn-sm btn-link text-danger">Delete page</button></form><?php endif; ?>
  </div></div>
<?php endif; ?></div></div>
<?php require __DIR__ . '/partials/footer.php';
