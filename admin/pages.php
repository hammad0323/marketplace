<?php
require __DIR__ . '/includes/init.php';
admin_require('content');
if (is_post()) {
    csrf_check();
    $id = input_int('id');
    if (input('act') === 'delete') { db_exec('DELETE FROM pages WHERE id = ?', [$id]); flash('success', 'Page deleted.'); redirect('admin/pages.php'); }
    $reserved = ['shop', 'search', 'category', 'product', 'brand', 'brands', 'offers', 'monthly-rashan', 'cart', 'checkout', 'order-success', 'track-order', 'login', 'register', 'logout', 'wishlist', 'my-account', 'contact', 'page', 'api', 'admin', 'assets', 'uploads'];
    $old = $id ? db_row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
    $slug = unique_slug('pages', input('slug') ?: input('title'), $id);
    if (in_array($slug, $reserved, true) || category_by_slug($slug)) $slug .= '-page';
    if (input('title') === '') { flash('danger', 'Title is required.'); redirect('admin/pages.php?new=1'); }
    $d = ['title' => mb_substr(input('title'), 0, 160), 'slug' => $slug, 'content' => $_POST['content'] ?? '', 'show_in_footer' => input_int('show_in_footer'), 'sort_order' => input_int('sort_order'), 'is_active' => input_int('is_active')];
    if ($id) { db_update_row('pages', $d, 'id = ?', [$id]); if ($old['slug'] !== $slug) seo_add_redirect('/' . $old['slug'] . '/', '/' . $slug . '/'); }
    else $id = db_insert_row('pages', $d);
    db_exec('DELETE FROM page_faqs WHERE page_id = ?', [$id]);
    foreach (input_arr('faq') as $i => $f) {
        $q = trim((string) ($f['q'] ?? '')); $a = trim((string) ($f['a'] ?? ''));
        if ($q !== '' && $a !== '') db_exec('INSERT INTO page_faqs (page_id, question, answer, sort_order) VALUES (?, ?, ?, ?)', [$id, mb_substr($q, 0, 255), $a, $i]);
    }
    seo_save('page', $id, input_arr('seo'));
    flash('success', 'Page saved.');
    redirect('admin/pages.php');
}
$edit = input_int('edit') ? db_row('SELECT * FROM pages WHERE id = ?', [input_int('edit')]) : null;
admin_header('Content Pages', 'pages');
if ($edit || input('new')):
    $p = $edit ?: ['id' => 0, 'title' => '', 'slug' => '', 'content' => '', 'show_in_footer' => 1, 'sort_order' => 0, 'is_active' => 1];
    $faqs = $p['id'] ? db_all('SELECT * FROM page_faqs WHERE page_id = ? ORDER BY sort_order', [$p['id']]) : [];
?>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
<div class="row g-3"><div class="col-lg-8">
  <div class="card mb-3"><div class="card-body">
    <?= af_text('title', 'Title *', $p['title'], ['required' => true, 'class' => 'form-control js-slug-source', 'data-slug' => '#f_slug']) ?>
    <?= af_text('slug', 'URL slug', $p['slug'], ['class' => 'form-control js-slug'], 'Page URL: ' . e(abs_url('')) . '<em>slug</em>/') ?>
    <?= af_textarea('content', 'Content (HTML allowed: p, h2, h3, ul, li, strong, a, table, img)', $p['content'], ['rows' => 14, 'class' => 'form-control font-monospace small']) ?>
  </div></div>
  <div class="card mb-3"><div class="card-header">FAQs on this page <small class="text-muted fw-normal">(shown visibly and as FAQ schema)</small></div><div class="card-body" id="faqBox">
    <?php foreach (array_merge($faqs, [['question' => '', 'answer' => '']]) as $i => $f): ?>
    <div class="border rounded p-2 mb-2"><input name="faq[<?= $i ?>][q]" class="form-control form-control-sm mb-1" placeholder="Question" value="<?= e($f['question']) ?>"><textarea name="faq[<?= $i ?>][a]" class="form-control form-control-sm" rows="2" placeholder="Answer"><?= e($f['answer']) ?></textarea></div>
    <?php endforeach; ?>
  </div><div class="card-footer bg-white"><button type="button" class="btn btn-sm btn-outline-brand" id="addFaq">Add FAQ</button></div></div>
  <div class="card"><div class="card-header">SEO</div><div class="card-body"><?= af_seo_fields(seo_get('page', (int) $p['id']), '', $p['slug']) ?></div></div>
</div><div class="col-lg-4"><div class="card"><div class="card-body"><?= af_text('sort_order', 'Order', $p['sort_order'], ['type' => 'number']) ?><?= af_check('show_in_footer', 'Show in footer', $p['show_in_footer']) ?><?= af_check('is_active', 'Published', $p['is_active']) ?>
  <button class="btn btn-brand w-100 mt-2">Save page</button><a href="<?= aurl('pages.php') ?>" class="btn btn-link w-100">Cancel</a></div></div></div></div></form>
<script>document.addEventListener('DOMContentLoaded', function () { var n = <?= count($faqs) + 1 ?>; $('#addFaq').on('click', function () { $('#faqBox').append('<div class="border rounded p-2 mb-2"><input name="faq[' + n + '][q]" class="form-control form-control-sm mb-1" placeholder="Question"><textarea name="faq[' + n + '][a]" class="form-control form-control-sm" rows="2" placeholder="Answer"></textarea></div>'); n++; }); });</script>
<?php else: $rows = db_all('SELECT p.*, (SELECT COUNT(*) FROM page_faqs WHERE page_id = p.id) AS faqs FROM pages p ORDER BY sort_order, title'); ?>
<div class="d-flex justify-content-end mb-3"><a href="?new=1" class="btn btn-brand"><i class="bi bi-plus-lg me-1"></i>New page</a></div>
<div class="card"><table class="table align-middle mb-0"><thead><tr><th>Title</th><th>URL</th><th>FAQs</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $p): ?><tr><td><strong><?= e($p['title']) ?></strong></td><td class="small"><a href="<?= url($p['slug'] . '/') ?>" target="_blank">/<?= e($p['slug']) ?>/</a></td><td><?= (int) $p['faqs'] ?></td>
  <td><?= $p['is_active'] ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Draft</span>' ?> <?= $p['show_in_footer'] ? '<span class="badge bg-light text-dark border">Footer</span>' : '' ?></td>
  <td class="text-end"><a href="?edit=<?= (int) $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a> <form method="post" class="d-inline" data-confirm="Delete page?"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif;
admin_footer();
