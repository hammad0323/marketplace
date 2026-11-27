<?php
/**
 * /manage/blog-edit?key=  — section-based blog builder.
 * key = existing blog id, or new-xxxx for a draft. No key = new draft.
 */
$me = require_admin('blogs');
$key = str_param('key', 30);
$row = null;
$doc = null;
if ($key !== '') {
    if (!blog_doc_key_ok($key)) {
        bo_redirect('admin', 'blogs');
    }
    if (ctype_digit($key)) {
        $row = get_blog((int)$key);
        if (!$row) {
            flash('danger', 'Blog not found.');
            bo_redirect('admin', 'blogs');
        }
    }
    $doc = blog_doc($key);
    if (!$doc && $row) {
        $doc = blog_doc_defaults($row);
    } elseif (!$doc) {
        flash('danger', 'Draft not found.');
        bo_redirect('admin', 'blogs');
    }
} else {
    $doc = blog_doc_defaults(null);
    $doc['status'] = 'draft';
    $doc['author'] = SITE_NAME;
}

function blog_blocks_from_post()
{
    $raw = (string)(isset($_POST['blocks_json']) ? $_POST['blocks_json'] : '[]');
    if (strlen($raw) > 800000) {
        throw new InvalidArgumentException('The content is too large.');
    }
    $blocks = json_decode($raw, true);
    if (!is_array($blocks)) {
        throw new InvalidArgumentException('The content could not be read. Please try again.');
    }
    $types = block_types();
    $out = array();
    foreach (array_slice($blocks, 0, 200) as $b) {
        if (!is_array($b) || !isset($b['type']) || !isset($types[$b['type']])) {
            continue;
        }
        $out[] = array('id' => preg_replace('/[^a-z0-9]/i', '', (string)(isset($b['id']) ? $b['id'] : '')) ?: 'b' . bin2hex(random_bytes(4)),
            'type' => $b['type'], 'data' => isset($b['data']) && is_array($b['data']) ? $b['data'] : array());
    }
    return $out;
}

if (is_post()) {
    require_post_csrf();
    $action = post('action', 'save');
    try {
        $heading = mb_substr(post('heading'), 0, 255, 'UTF-8');
        if ($heading === '') {
            throw new InvalidArgumentException('The title is required.');
        }
        $slug = blog_slugify(post('slug') !== '' ? post('slug') : $heading);
        if ($slug === '') {
            throw new InvalidArgumentException('Enter a URL slug using letters or numbers.');
        }
        $dupe = db_val('SELECT id FROM blogs WHERE url = ? AND id <> ?', 'si', array($slug, $row ? (int)$row['id'] : 0));
        if ($dupe) {
            throw new InvalidArgumentException('Another blog already uses the URL “' . $slug . '”.');
        }
        $status = in_array(post('status'), array('draft', 'published', 'scheduled'), true) ? post('status') : 'draft';
        $publishAt = '';
        if ($status === 'scheduled') {
            $ts = strtotime(str_replace('T', ' ', post('publish_at')));
            if (!$ts) {
                throw new InvalidArgumentException('Choose the date and time to publish.');
            }
            $publishAt = date('Y-m-d H:i', $ts);
        }
        $seoIn = isset($_POST['seo']) && is_array($_POST['seo']) ? $_POST['seo'] : array();
        $canonical = trim((string)(isset($seoIn['canonical']) ? $seoIn['canonical'] : ''));
        if ($canonical !== '' && !preg_match('#^(https://|/)#', $canonical)) {
            throw new InvalidArgumentException('The canonical URL must start with https:// or /.');
        }
        $new = array_merge($doc, array(
            'blog_id' => $row ? (int)$row['id'] : null,
            'status' => $status,
            'publish_at' => $publishAt,
            'heading' => $heading,
            'slug' => $slug,
            'excerpt' => mb_substr(post('excerpt'), 0, 500, 'UTF-8'),
            'author' => mb_substr(post('author') !== '' ? post('author') : SITE_NAME, 0, 100, 'UTF-8'),
            'featured_image' => mb_substr(post('featured_image'), 0, 500),
            'city_id' => post_int('city_id'),
            'seo' => array(
                'title' => mb_substr(trim((string)(isset($seoIn['title']) ? $seoIn['title'] : '')), 0, 160, 'UTF-8'),
                'description' => mb_substr(trim((string)(isset($seoIn['description']) ? $seoIn['description'] : '')), 0, 320, 'UTF-8'),
                'canonical' => mb_substr($canonical, 0, 500),
                'og_image' => mb_substr(trim((string)(isset($seoIn['og_image']) ? $seoIn['og_image'] : '')), 0, 500),
                'noindex' => !empty($seoIn['noindex']),
            ),
            'blocks' => blog_blocks_from_post(),
            'updated_by' => $me['id'],
        ));
        if ($action === 'preview') {
            storage_write('blog_previews/' . ($key !== '' ? $key : 'new') . '.json', $new + array('key' => $key));
            if ($key === '') {
                // Unsaved new blog: save it as a draft first so the preview has a key
                $key = 'new-' . bin2hex(random_bytes(6));
                $new['status'] = 'draft';
                blog_doc_save($key, $new);
                storage_write('blog_previews/' . $key . '.json', $new + array('key' => $key));
            }
            json_out(array('ok' => true, 'url' => url('/blog-preview/' . $key . '?v=' . time()), 'key' => $key));
        }
        if ($row) {
            // Existing blog: keep the row in step with the builder (heading, url, author, image, city)
            db_exec('UPDATE blogs SET heading = ?, url = ?, author = ?, profile_image = ?, city_id = ? WHERE id = ?',
                'ssssii', array($heading, $slug, $new['author'], $new['featured_image'], $new['city_id'] ?: null, (int)$row['id']));
            if (post('guide_edit') === '1') {
                db_exec('UPDATE blogs SET quick_answer_text = ?, population = ?, no_of_mosque = ?, map_url = ?, summary_text = ? WHERE id = ?', 'sssssi', array(
                    safe_rich_html(post('guide_quick')), mb_substr(post('guide_population'), 0, 2000, 'UTF-8'), mb_substr(post('guide_count'), 0, 100),
                    post('guide_map') !== '' ? (map_embed_src(post('guide_map')) !== '' ? post('guide_map') : $row['map_url']) : '', safe_rich_html(post('guide_summary')), (int)$row['id']));
            }
            blog_doc_save((string)$row['id'], $new);
            log_me('blog.update', 'blog', (int)$row['id'], null, array('heading' => $heading, 'status' => $status, 'blocks' => count($new['blocks'])));
            flash('success', $status === 'published' ? 'Blog saved and published.' : ($status === 'scheduled' ? 'Blog saved and scheduled for ' . $publishAt . '.' : 'Saved. This blog is hidden from the website while it is a draft.'));
            $dest = (string)$row['id'];
        } else {
            $dkey = $key !== '' ? $key : 'new-' . bin2hex(random_bytes(6));
            blog_doc_save($dkey, $new);
            $dest = $dkey;
            if ($status === 'published' || ($status === 'scheduled' && strtotime($publishAt) <= time())) {
                $new['excerpt'] = (string)$new['excerpt'];
                $id = blog_insert_row($dkey, $new);
                $dest = (string)$id;
                log_me('blog.publish', 'blog', $id, null, array('heading' => $heading));
                flash('success', 'Blog published.');
            } else {
                log_me('blog.save_draft', 'blog', null, null, array('key' => $dkey, 'heading' => $heading, 'status' => $status));
                flash('success', $status === 'scheduled' ? 'Scheduled for ' . $publishAt . '.' : 'Draft saved.');
            }
        }
        storage_delete('blog_previews/' . $dest . '.json');
        bo_redirect('admin', 'blog-edit?key=' . $dest);
    } catch (InvalidArgumentException $e) {
        if ($action === 'preview') {
            json_out(array('ok' => false, 'message' => $e->getMessage()), 422);
        }
        flash('danger', $e->getMessage());
        $_SESSION['blog_unsaved'] = $_POST;
        bo_redirect('admin', 'blog-edit' . ($key !== '' ? '?key=' . $key : ''));
    }
}

// Restore unsaved input after a validation error
if (!empty($_SESSION['blog_unsaved'])) {
    $u = $_SESSION['blog_unsaved'];
    unset($_SESSION['blog_unsaved']);
    foreach (array('heading', 'slug', 'excerpt', 'author', 'featured_image') as $k) {
        if (isset($u[$k])) {
            $doc[$k] = $u[$k];
        }
    }
    $restored = json_decode(isset($u['blocks_json']) ? $u['blocks_json'] : '', true);
    if (is_array($restored)) {
        $doc['blocks'] = $restored;
    }
}

// Data for the builder UI
$catsByGc = array();
foreach (db_all('SELECT category_id, grand_category_id, TRIM(name) name FROM th_categories ORDER BY arrangement, name') as $c) {
    $catsByGc[(int)$c['grand_category_id']][] = array('id' => (int)$c['category_id'], 'name' => $c['name']);
}
$cityNames = array();
foreach ((array)$doc['blocks'] as $bl) {
    if (is_array($bl) && isset($bl['data']) && is_array($bl['data']) && !empty($bl['data']['city'])) {
        $c = get_city((int)$bl['data']['city']);
        if ($c) {
            $cityNames[(int)$c['city_id']] = $c['city_title'] . ', ' . $c['state_title'];
        }
    }
}
$builderCfg = array(
    'types' => block_types(),
    'categories' => $catsByGc,
    'cityNames' => $cityNames,
    'api' => url(ADMIN_PATH . '/api'),
    'upload' => url(ADMIN_PATH . '/api?do=upload'),
    'mediaBase' => MEDIA_URL,
    'hasLegacy' => (bool)$row,
);
$featuredUrl = blog_image_url($doc['featured_image']);
$seo = array_merge(array('title' => '', 'description' => '', 'canonical' => '', 'og_image' => '', 'noindex' => false), (array)$doc['seo']);
$title = $key === '' ? 'New blog' : 'Edit blog';
bo_start('admin', $title, array('editor' => true, 'leaflet' => true, 'sortable' => true, 'nav' => 'blogs'));
?>
<form method="post" class="blog-builder" data-blog-builder novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="blocks_json" data-blocks-json>
  <script type="application/json" data-blocks-initial><?= json_encode(array_values((array)$doc['blocks']), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script type="application/json" data-builder-config><?= json_encode($builderCfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card bo-card mb-4">
        <div class="card-body">
          <label class="visually-hidden" for="bh">Title</label>
          <input class="form-control form-control-lg blog-title-input" id="bh" name="heading" value="<?= e($doc['heading']) ?>" placeholder="Blog title" maxlength="255" required data-slug-source>
          <div class="input-group input-group-sm mt-2">
            <span class="input-group-text"><?= e(SITE_URL) ?>/blog/</span>
            <input class="form-control" name="slug" value="<?= e($doc['slug']) ?>" placeholder="url-slug" data-slug-target<?= $doc['slug'] !== '' ? ' data-touched="1"' : '' ?>>
            <?php if ($row): ?><span class="input-group-text">/<?= (int)$row['id'] ?></span><?php endif; ?>
          </div>
          <label class="form-label small mt-3" for="bx">Short description</label>
          <textarea class="form-control" id="bx" name="excerpt" rows="2" maxlength="500" data-count="160" placeholder="Shown on blog cards and under the title"><?= e($doc['excerpt']) ?></textarea>
        </div>
      </div>

      <div class="d-flex align-items-center mb-2">
        <h2 class="h6 m-0">Content sections</h2>
        <span class="small text-muted ms-2" data-block-count></span>
        <button type="button" class="btn btn-sm btn-light ms-auto" data-collapse-all>Collapse all</button>
      </div>
      <div class="block-list" data-block-list></div>
      <div class="block-empty text-center text-muted py-5" data-block-empty hidden><i class="bi bi-grid-1x2 fs-2 d-block mb-2"></i>No sections yet. Add your first one.</div>
      <button type="button" class="btn btn-outline-primary w-100 add-block-btn mt-2" data-add-block><i class="bi bi-plus-lg me-1"></i>Add a section</button>
    </div>

    <div class="col-xl-4">
      <div class="bo-sticky">
        <div class="card bo-card mb-4">
          <div class="card-header"><h2 class="h6 m-0">Publish</h2></div>
          <div class="card-body">
            <label class="form-label small" for="bst">Status</label>
            <select class="form-select" id="bst" name="status" data-status-select>
              <option value="draft"<?= sel('draft', $doc['status']) ?>>Draft (hidden)</option>
              <option value="published"<?= sel('published', $doc['status']) ?>>Published</option>
              <option value="scheduled"<?= sel('scheduled', $doc['status']) ?>>Scheduled</option>
            </select>
            <div class="mt-2" data-schedule-box<?= $doc['status'] === 'scheduled' ? '' : ' hidden' ?>><label class="form-label small" for="bpa">Publish on</label><input class="form-control" type="datetime-local" id="bpa" name="publish_at" value="<?= e($doc['publish_at'] !== '' ? date('Y-m-d\TH:i', strtotime($doc['publish_at'])) : '') ?>"><div class="form-text">Server time zone: <?= e(date_default_timezone_get()) ?></div></div>
            <?php if (!empty($doc['updated_at'])): ?><p class="small text-muted mt-2 mb-0">Last saved <?= e(date('j M Y H:i', strtotime($doc['updated_at']))) ?></p><?php endif; ?>
          </div>
          <div class="card-footer d-flex flex-wrap gap-2">
            <button class="btn btn-primary flex-grow-1" name="action" value="save"><i class="bi bi-check2 me-1"></i>Save</button>
            <button type="button" class="btn btn-outline-secondary" data-preview><i class="bi bi-eye me-1"></i>Preview</button>
            <?php if ($row && $doc['status'] === 'published'): ?><a class="btn btn-light w-100" href="<?= e(url(blog_url($row))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View live</a><?php endif; ?>
          </div>
        </div>

        <div class="card bo-card mb-4">
          <div class="card-header"><h2 class="h6 m-0">Featured image</h2></div>
          <div class="card-body">
            <div class="img-preview img-preview-cover mb-2" data-featured-preview><?php if ($featuredUrl !== ''): ?><img src="<?= e($featuredUrl) ?>" alt="" onerror="this.remove()"><?php endif; ?></div>
            <input type="hidden" name="featured_image" value="<?= e($doc['featured_image']) ?>" data-featured-input>
            <div class="d-flex gap-2"><label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload me-1"></i>Upload<input type="file" accept="image/*" hidden data-upload-to="[data-featured-input]" data-preview-to="[data-featured-preview]"></label><button type="button" class="btn btn-sm btn-light" data-clear-featured>Remove</button></div>
          </div>
        </div>

        <div class="card bo-card mb-4">
          <div class="card-header"><h2 class="h6 m-0">Details</h2></div>
          <div class="card-body">
            <label class="form-label small">Author</label><input class="form-control mb-3" name="author" value="<?= e($doc['author']) ?>" maxlength="100">
            <label class="form-label small">City (optional — adds city links)</label><?php bo_city_select('city_id', (int)$doc['city_id'], false, 'bcity'); ?>
          </div>
        </div>

        <div class="card bo-card mb-4">
          <div class="card-header"><h2 class="h6 m-0">SEO &amp; sharing</h2></div>
          <div class="card-body">
            <label class="form-label small">SEO title</label><input class="form-control mb-2" name="seo[title]" value="<?= e($seo['title']) ?>" maxlength="160" data-count="60" placeholder="Defaults to the blog title">
            <label class="form-label small">Meta description</label><textarea class="form-control mb-2" name="seo[description]" rows="3" maxlength="320" data-count="158" placeholder="Defaults to the short description"><?= e($seo['description']) ?></textarea>
            <label class="form-label small">Canonical URL</label><input class="form-control mb-2" name="seo[canonical]" value="<?= e($seo['canonical']) ?>" placeholder="Leave empty (recommended)">
            <label class="form-label small">Social share image</label><input class="form-control mb-2" name="seo[og_image]" value="<?= e($seo['og_image']) ?>" placeholder="Defaults to the featured image">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="seo[noindex]" value="1" id="bni"<?= chk(!empty($seo['noindex'])) ?>><label class="form-check-label small" for="bni">Hide from search engines (noindex)</label></div>
            <div class="serp mt-3" aria-hidden="true"><div class="serp-url"><?= e(SITE_URL) ?>/blog/…</div><div class="serp-title" data-serp-title></div><div class="serp-desc" data-serp-desc></div></div>
            <p class="small text-muted mt-2 mb-0">Article and FAQ structured data are added automatically.</p>
          </div>
        </div>

        <?php if ($row): ?>
        <div class="card bo-card mb-4">
          <div class="card-header"><button class="btn btn-link p-0 text-decoration-none fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#guideData">City guide data (original content) <i class="bi bi-chevron-down small"></i></button></div>
          <div class="collapse" id="guideData"><div class="card-body">
            <input type="hidden" name="guide_edit" value="0" data-guide-flag>
            <p class="small text-muted">Edits the original fields of this guide, shown by the “City guide (original content)” section.</p>
            <label class="form-label small">Quick answer</label><?php rich_editor('guide_quick', (string)$row['quick_answer_text'], 'guide_quick', 140); ?>
            <label class="form-label small mt-2">Muslim population</label><input class="form-control form-control-sm" name="guide_population" value="<?= e((string)$row['population']) ?>">
            <label class="form-label small mt-2">Number of mosques (text)</label><input class="form-control form-control-sm" name="guide_count" value="<?= e((string)$row['no_of_mosque']) ?>">
            <label class="form-label small mt-2">Google Maps embed (iframe)</label><textarea class="form-control form-control-sm font-monospace" name="guide_map" rows="2"><?= e((string)$row['map_url']) ?></textarea>
            <label class="form-label small mt-2">Summary</label><?php rich_editor('guide_summary', (string)$row['summary_text'], 'guide_summary', 160); ?>
          </div></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>

<div class="modal fade" id="blockPicker" tabindex="-1" aria-labelledby="blockPickerTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="blockPickerTitle">Add a section</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><div class="block-picker" data-block-picker></div></div>
  </div></div>
</div>
<?php bo_end('<script src="' . e(asset('js/blog-builder.js')) . '"></script>');
