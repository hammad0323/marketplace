<?php
/** /manage/page-edit?slug= — edit a static page: title, rich content, optional sections, SEO, status. */
require_admin('pages');
$slug = str_param('slug', 80);
$doc = $slug !== '' ? page_doc($slug) : null;
if ($slug !== '' && !$doc) {
    flash('danger', 'Page not found.');
    bo_redirect('admin', 'pages');
}
if (is_post()) {
    require_post_csrf();
    $newSlug = blog_slugify(post('slug') !== '' ? post('slug') : post('title'));
    $err = '';
    if (post('title') === '') {
        $err = 'The title is required.';
    } elseif (!page_slug_ok($newSlug)) {
        $err = 'Use lowercase letters, numbers and dashes for the address.';
    } elseif (in_array($newSlug, page_reserved_slugs(), true)) {
        $err = 'That address is used by another part of the website.';
    } elseif ($newSlug !== $slug && is_file(storage_path('pages/' . $newSlug . '.json'))) {
        $err = 'A page with that address already exists.';
    }
    $blocks = json_decode((string)post('blocks_json', '[]'), true);
    if ($err !== '') {
        flash('danger', $err);
        bo_redirect('admin', 'page-edit' . ($slug !== '' ? '?slug=' . $slug : ''));
    }
    $types = block_types();
    $clean = array();
    foreach (is_array($blocks) ? array_slice($blocks, 0, 100) : array() as $b) {
        if (is_array($b) && isset($b['type'], $types[$b['type']]) && $b['type'] !== 'legacy_guide') {
            $clean[] = array('id' => preg_replace('/[^a-z0-9]/i', '', (string)$b['id']) ?: 'b' . bin2hex(random_bytes(4)), 'type' => $b['type'], 'data' => isset($b['data']) && is_array($b['data']) ? $b['data'] : array());
        }
    }
    $seo = isset($_POST['seo']) && is_array($_POST['seo']) ? $_POST['seo'] : array();
    $new = array(
        'slug' => $newSlug,
        'title' => mb_substr(post('title'), 0, 150, 'UTF-8'),
        'subtitle' => mb_substr(post('subtitle'), 0, 250, 'UTF-8'),
        'content' => safe_rich_html(post('content')),
        'blocks' => $clean,
        'status' => post('status') === 'published' ? 'published' : 'draft',
        'in_footer' => post('in_footer') === '1',
        'seo' => array('title' => mb_substr(trim((string)(isset($seo['title']) ? $seo['title'] : '')), 0, 160, 'UTF-8'), 'description' => mb_substr(trim((string)(isset($seo['description']) ? $seo['description'] : '')), 0, 320, 'UTF-8'), 'noindex' => !empty($seo['noindex'])),
        'updated_at' => date('Y-m-d H:i:s'),
    );
    storage_write('pages/' . $newSlug . '.json', $new);
    if ($slug !== '' && $slug !== $newSlug && is_file(storage_path('pages/' . $slug . '.json'))) {
        storage_delete('pages/' . $slug . '.json');
    }
    log_me($slug === '' ? 'page.create' : 'page.update', 'page', null, null, array('slug' => $newSlug, 'title' => $new['title'], 'status' => $new['status']));
    flash('success', 'Page saved.');
    if (post('action') === 'preview') {
        header('Location: ' . url('/page-preview/' . $newSlug), true, 303);
        exit;
    }
    bo_redirect('admin', 'page-edit?slug=' . $newSlug);
}
$doc = $doc ? $doc : array('slug' => '', 'title' => '', 'subtitle' => '', 'content' => '', 'blocks' => array(), 'status' => 'draft', 'in_footer' => false, 'seo' => array('title' => '', 'description' => '', 'noindex' => false));
$builderCfg = array('types' => array_diff_key(block_types(), array('legacy_guide' => 1)), 'categories' => array(), 'cityNames' => array(), 'api' => url(ADMIN_PATH . '/api'), 'upload' => url(ADMIN_PATH . '/api?do=upload'), 'mediaBase' => MEDIA_URL, 'hasLegacy' => false);
foreach (db_all('SELECT category_id, grand_category_id, TRIM(name) name FROM th_categories ORDER BY arrangement, name') as $c) {
    $builderCfg['categories'][(int)$c['grand_category_id']][] = array('id' => (int)$c['category_id'], 'name' => $c['name']);
}
bo_start('admin', $slug === '' ? 'New page' : 'Edit page', array('editor' => true, 'leaflet' => true, 'sortable' => true, 'nav' => 'pages'));
?>
<?php if (!empty($doc['source'])): ?><div class="alert alert-info">This page is currently read from <code>content/pages/<?= e($slug) ?>.html</code>. Saving here creates an editable version that replaces it.</div><?php endif; ?>
<form method="post" class="blog-builder" data-blog-builder novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="blocks_json" data-blocks-json>
  <script type="application/json" data-blocks-initial><?= json_encode(array_values((array)$doc['blocks']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script type="application/json" data-builder-config><?= json_encode($builderCfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card bo-card mb-4"><div class="card-body">
        <input class="form-control form-control-lg blog-title-input" name="title" value="<?= e($doc['title']) ?>" placeholder="Page title" maxlength="150" required data-slug-source>
        <div class="input-group input-group-sm mt-2"><span class="input-group-text"><?= e(SITE_URL) ?>/</span><input class="form-control" name="slug" value="<?= e($doc['slug']) ?>" placeholder="page-address" data-slug-target<?= $doc['slug'] !== '' ? ' data-touched="1"' : '' ?>></div>
        <label class="form-label small mt-3">Subtitle</label><input class="form-control" name="subtitle" value="<?= e($doc['subtitle']) ?>" maxlength="250">
        <label class="form-label small mt-3">Content</label><?php rich_editor('content', $doc['content'], 'pcontent', 360); ?>
      </div></div>
      <div class="d-flex align-items-center mb-2"><h2 class="h6 m-0">Extra sections (optional)</h2><span class="small text-muted ms-2" data-block-count></span></div>
      <div class="block-list" data-block-list></div>
      <div class="block-empty text-center text-muted py-4" data-block-empty hidden>Add FAQs, maps, mosque cards, calls to action and more.</div>
      <button type="button" class="btn btn-outline-primary w-100 add-block-btn mt-2" data-add-block><i class="bi bi-plus-lg me-1"></i>Add a section</button>
    </div>
    <div class="col-xl-4"><div class="bo-sticky">
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">Publish</h2></div>
        <div class="card-body">
          <select class="form-select mb-3" name="status"><option value="draft">Draft (hidden)</option><option value="published"<?= sel('published', $doc['status']) ?>>Published</option></select>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="in_footer" value="1" id="inf"<?= chk(!empty($doc['in_footer'])) ?>><label class="form-check-label" for="inf">Link in the footer</label></div>
        </div>
        <div class="card-footer d-flex gap-2"><button class="btn btn-primary flex-grow-1" name="action" value="save">Save</button><button class="btn btn-outline-secondary" name="action" value="preview" formtarget="_blank"><i class="bi bi-eye me-1"></i>Save &amp; preview</button></div>
      </div>
      <div class="card bo-card mb-4">
        <div class="card-header"><h2 class="h6 m-0">SEO</h2></div>
        <div class="card-body">
          <label class="form-label small">SEO title</label><input class="form-control mb-2" name="seo[title]" value="<?= e($doc['seo']['title']) ?>" maxlength="160" data-count="60">
          <label class="form-label small">Meta description</label><textarea class="form-control mb-2" name="seo[description]" rows="3" maxlength="320" data-count="158"><?= e($doc['seo']['description']) ?></textarea>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="seo[noindex]" value="1" id="pni"<?= chk(!empty($doc['seo']['noindex'])) ?>><label class="form-check-label small" for="pni">Hide from search engines</label></div>
        </div>
      </div>
    </div></div>
  </div>
</form>
<div class="modal fade" id="blockPicker" tabindex="-1" aria-labelledby="blockPickerTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="blockPickerTitle">Add a section</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><div class="block-picker" data-block-picker></div></div>
  </div></div>
</div>
<?php bo_end('<script src="' . e(asset('js/blog-builder.js')) . '"></script>');
