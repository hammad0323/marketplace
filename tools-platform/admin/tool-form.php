<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$tool = $id ? get_tool($id) : null;
$content = $id ? tp_query_one('SELECT * FROM tool_content WHERE tool_id = ?', 'i', [$id]) : null;
$faqs = $id ? tp_query('SELECT * FROM tool_faqs WHERE tool_id = ? ORDER BY sort_order', 'i', [$id]) : [];
$examples = $id ? tp_query('SELECT * FROM tool_examples WHERE tool_id = ? ORDER BY sort_order', 'i', [$id]) : [];
$formulas = $id ? tp_query('SELECT * FROM tool_formulas WHERE tool_id = ? ORDER BY sort_order', 'i', [$id]) : [];
$seo = $id ? (get_seo_settings('tool', $id) ?? []) : [];
$relatedIds = $id ? array_column(tp_query('SELECT related_tool_id FROM tool_related WHERE tool_id = ?', 'i', [$id]), 'related_tool_id') : [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();

    $name = tp_sanitize_text($_POST['name'] ?? '', 180);
    $slugInput = tp_sanitize_text($_POST['slug'] ?? '', 200);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $shortDescription = tp_sanitize_text($_POST['short_description'] ?? '', 300);
    $description = tp_sanitize_text($_POST['description'] ?? '', 5000);
    $icon = tp_sanitize_text($_POST['icon'] ?? 'bi-calculator', 80);
    $featuredImage = tp_sanitize_text($_POST['featured_image'] ?? '', 255);
    $toolFile = tp_sanitize_text($_POST['tool_file'] ?? '', 150);
    $toolType = tp_sanitize_text($_POST['tool_type'] ?? 'calculator', 40);
    $status = tp_sanitize_text($_POST['status'] ?? 'draft', 20);
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $isPopular = isset($_POST['is_popular']) ? 1 : 0;
    $isTrending = isset($_POST['is_trending']) ? 1 : 0;

    if ($name === '') { $errors[] = 'Tool name is required.'; }
    if (!$categoryId) { $errors[] = 'Please select a category.'; }
    if ($toolFile === '') { $errors[] = 'Please select the calculator logic file for this tool.'; }

    if (!$errors) {
        $slug = tp_unique_slug('tools', $slugInput !== '' ? $slugInput : $name, $id ?: null);
        $oldSlug = $tool['slug'] ?? null;

        if ($tool) {
            tp_execute(
                'UPDATE tools SET category_id=?, name=?, slug=?, short_description=?, description=?, icon=?, featured_image=?, tool_file=?, tool_type=?, status=?, is_featured=?, is_popular=?, is_trending=?, sort_order=? WHERE id=?',
                'isssssssssiiiii',
                [$categoryId, $name, $slug, $shortDescription, $description, $icon, $featuredImage, $toolFile, $toolType, $status, $isFeatured, $isPopular, $isTrending, $sortOrder, $id]
            );
        } else {
            $result = tp_execute(
                'INSERT INTO tools (category_id, name, slug, short_description, description, icon, featured_image, tool_file, tool_type, status, is_featured, is_popular, is_trending, sort_order)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                'isssssssssiiii',
                [$categoryId, $name, $slug, $shortDescription, $description, $icon, $featuredImage, $toolFile, $toolType, $status, $isFeatured, $isPopular, $isTrending, $sortOrder]
            );
            $id = $result['insert_id'];
        }

        // Content block (upsert)
        $contentFields = [
            'introduction' => tp_sanitize_text($_POST['introduction'] ?? '', 8000),
            'how_to_use' => tp_sanitize_text($_POST['how_to_use'] ?? '', 8000),
            'formula' => tp_sanitize_text($_POST['formula'] ?? '', 2000),
            'formula_explanation' => tp_sanitize_text($_POST['formula_explanation'] ?? '', 4000),
            'calculation_method' => tp_sanitize_text($_POST['calculation_method'] ?? '', 4000),
            'benefits' => tp_sanitize_text($_POST['benefits'] ?? '', 4000),
            'common_mistakes' => tp_sanitize_text($_POST['common_mistakes'] ?? '', 4000),
            'tips' => tp_sanitize_text($_POST['tips'] ?? '', 4000),
            'notes' => tp_sanitize_text($_POST['notes'] ?? '', 4000),
            'disclaimer' => tp_sanitize_text($_POST['disclaimer'] ?? '', 1000),
        ];
        tp_execute('DELETE FROM tool_content WHERE tool_id = ?', 'i', [$id]);
        tp_execute(
            'INSERT INTO tool_content (tool_id, introduction, how_to_use, formula, formula_explanation, calculation_method, benefits, common_mistakes, tips, notes, disclaimer)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            'issssssssss',
            [$id, ...array_values($contentFields)]
        );

        // FAQs
        tp_execute('DELETE FROM tool_faqs WHERE tool_id = ?', 'i', [$id]);
        foreach ($_POST['faq_question'] ?? [] as $i => $q) {
            $q = tp_sanitize_text($q, 300);
            $a = tp_sanitize_text($_POST['faq_answer'][$i] ?? '', 3000);
            if ($q !== '' && $a !== '') {
                tp_execute('INSERT INTO tool_faqs (tool_id, question, answer, sort_order) VALUES (?,?,?,?)', 'issi', [$id, $q, $a, $i]);
            }
        }

        // Formulas
        tp_execute('DELETE FROM tool_formulas WHERE tool_id = ?', 'i', [$id]);
        foreach ($_POST['formula_label'] ?? [] as $i => $label) {
            $text = tp_sanitize_text($_POST['formula_text'][$i] ?? '', 2000);
            if ($text !== '') {
                tp_execute('INSERT INTO tool_formulas (tool_id, label, formula_text, sort_order) VALUES (?,?,?,?)', 'issi', [$id, tp_sanitize_text($label, 200), $text, $i]);
            }
        }

        // Examples
        tp_execute('DELETE FROM tool_examples WHERE tool_id = ?', 'i', [$id]);
        foreach ($_POST['example_title'] ?? [] as $i => $title) {
            $input = tp_sanitize_text($_POST['example_input'][$i] ?? '', 1000);
            $output = tp_sanitize_text($_POST['example_output'][$i] ?? '', 1000);
            if ($input !== '' || $output !== '') {
                tp_execute('INSERT INTO tool_examples (tool_id, title, input_summary, output_summary, sort_order) VALUES (?,?,?,?,?)', 'isssi', [$id, tp_sanitize_text($title, 200), $input, $output, $i]);
            }
        }

        // Related tools
        tp_execute('DELETE FROM tool_related WHERE tool_id = ?', 'i', [$id]);
        foreach ($_POST['related_tools'] ?? [] as $i => $relId) {
            $relId = (int) $relId;
            if ($relId && $relId !== $id) {
                tp_execute('INSERT IGNORE INTO tool_related (tool_id, related_tool_id, sort_order) VALUES (?,?,?)', 'iii', [$id, $relId, $i]);
            }
        }

        // SEO
        $seoFields = [
            'seo_title' => tp_sanitize_text($_POST['seo_title'] ?? '', 160),
            'meta_description' => tp_sanitize_text($_POST['meta_description'] ?? '', 320),
            'focus_keyword' => tp_sanitize_text($_POST['focus_keyword'] ?? '', 150),
            'secondary_keywords' => tp_sanitize_text($_POST['secondary_keywords'] ?? '', 400),
            'canonical_url' => tp_sanitize_text($_POST['canonical_url'] ?? '', 255),
            'robots' => tp_sanitize_text($_POST['robots'] ?? 'index,follow', 60),
            'og_title' => tp_sanitize_text($_POST['og_title'] ?? '', 160),
            'og_description' => tp_sanitize_text($_POST['og_description'] ?? '', 320),
            'og_image' => tp_sanitize_text($_POST['og_image'] ?? '', 255),
            'twitter_title' => tp_sanitize_text($_POST['twitter_title'] ?? '', 160),
            'twitter_description' => tp_sanitize_text($_POST['twitter_description'] ?? '', 320),
            'twitter_image' => tp_sanitize_text($_POST['twitter_image'] ?? '', 255),
            'schema_type' => tp_sanitize_text($_POST['schema_type'] ?? 'WebApplication', 60),
            'breadcrumb_title' => tp_sanitize_text($_POST['breadcrumb_title'] ?? '', 150),
            'image_alt_text' => tp_sanitize_text($_POST['image_alt_text'] ?? '', 200),
        ];
        save_seo_settings('tool', $id, $seoFields);

        // Recompute + store SEO score
        $freshFaqs = tp_query('SELECT * FROM tool_faqs WHERE tool_id = ?', 'i', [$id]);
        $freshExamples = tp_query('SELECT * FROM tool_examples WHERE tool_id = ?', 'i', [$id]);
        $freshFormulas = tp_query('SELECT * FROM tool_formulas WHERE tool_id = ?', 'i', [$id]);
        $relatedCount = (int) (tp_query_one('SELECT COUNT(*) c FROM tool_related WHERE tool_id = ?', 'i', [$id])['c'] ?? 0);
        $ctx = tp_seo_context_for_tool(
            ['name' => $name, 'slug' => $slug, 'short_description' => $shortDescription, 'description' => $description, 'featured_image' => $featuredImage],
            $seoFields,
            $contentFields,
            $freshFaqs,
            $freshExamples,
            $freshFormulas,
            $relatedCount
        );
        $scoreResult = calculate_seo_score($ctx);
        tp_execute('UPDATE tools SET seo_score = ? WHERE id = ?', 'ii', [$scoreResult['score'], $id]);

        if ($oldSlug && $oldSlug !== $slug) {
            tp_delete_route_file($oldSlug);
            tp_execute('INSERT INTO redirects (old_url, new_url, redirect_type) VALUES (?, ?, 301)', 'ss', ['/' . $oldSlug, '/' . $slug]);
        }
        tp_write_tool_route($slug);

        tp_log_activity($_SESSION['admin_id'], $tool ? 'update_tool' : 'create_tool', 'tool', $id, $name);
        tp_flash_set('success', 'Tool saved successfully. SEO score: ' . $scoreResult['score'] . '% (' . $scoreResult['grade'] . ')');
        header('Location: ' . tp_url('admin/tool-form.php?id=' . $id));
        exit;
    }
}

$categories = get_categories(false);
$toolFiles = tp_list_tool_files();
$allTools = $id ? tp_query('SELECT id, name FROM tools WHERE id != ? ORDER BY name', 'i', [$id]) : tp_query('SELECT id, name FROM tools ORDER BY name');

$adminPageTitle = $tool ? 'Edit Tool: ' . $tool['name'] : 'Add New Tool';
require __DIR__ . '/includes/admin-header.php';
?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="row">
<div class="col-lg-9">
<form method="post" id="tpToolForm">
  <?= tp_csrf_field() ?>
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-basic">Basic Info</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-content">Content</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-seo">SEO</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-related">Related Tools</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-display">Display</a></li>
  </ul>
  <div class="tab-content">

    <div class="tab-pane fade show active" id="tab-basic">
      <div class="admin-card">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Tool Name</label>
            <input type="text" name="name" class="form-control" data-slug-source value="<?= e($tool['name'] ?? '') ?>" required></div>
          <div class="col-md-6"><label class="form-label">Slug (URL: /slug)</label>
            <input type="text" name="slug" class="form-control" data-slug-target data-existing="<?= $tool ? 1 : 0 ?>" data-original="<?= e($tool['slug'] ?? '') ?>" value="<?= e($tool['slug'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
              <option value="">Select category...</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) ($tool['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-6"><label class="form-label">Tool Type</label>
            <select name="tool_type" class="form-select">
              <?php foreach (['calculator','converter','generator','formatter','validator','encoder','decoder','timer','counter','analyzer','image_tool','developer_tool','financial_tool','utility'] as $type): ?>
                <option value="<?= $type ?>" <?= ($tool['tool_type'] ?? 'calculator') === $type ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$type)) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-12"><label class="form-label">Short Description</label>
            <input type="text" name="short_description" class="form-control" maxlength="300" value="<?= e($tool['short_description'] ?? '') ?>"></div>
          <div class="col-md-12"><label class="form-label">Full Description</label>
            <textarea name="description" class="form-control" rows="3"><?= e($tool['description'] ?? '') ?></textarea></div>
          <div class="col-md-6"><label class="form-label">Icon (Bootstrap Icon class)</label>
            <input type="text" name="icon" class="form-control" value="<?= e($tool['icon'] ?? 'bi-calculator') ?>"></div>
          <div class="col-md-6"><label class="form-label">Featured Image URL</label>
            <input type="text" name="featured_image" class="form-control" value="<?= e($tool['featured_image'] ?? '') ?>"></div>
          <div class="col-md-12"><label class="form-label">Calculator Logic File</label>
            <select name="tool_file" class="form-select tp-select2" required>
              <option value="">Select the logic file for this tool...</option>
              <?php foreach ($toolFiles as $f): ?>
                <option value="<?= e($f) ?>" <?= ($tool['tool_file'] ?? '') === $f ? 'selected' : '' ?>><?= e($f) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">New calculators are added by a developer as a PHP file under <code>/tools/&lt;category&gt;/</code>; pick it here once it exists.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-content">
      <div class="admin-card mb-3">
        <div class="row g-3">
          <div class="col-md-12"><label class="form-label">Introduction (What is this tool?)</label>
            <textarea name="introduction" class="form-control" rows="4"><?= e($content['introduction'] ?? '') ?></textarea></div>
          <div class="col-md-12"><label class="form-label">How To Use (one step per line — powers the HowTo schema)</label>
            <textarea name="how_to_use" class="form-control" rows="4"><?= e($content['how_to_use'] ?? '') ?></textarea></div>
          <div class="col-md-12"><label class="form-label">Formula</label>
            <textarea name="formula" class="form-control" rows="2"><?= e($content['formula'] ?? '') ?></textarea></div>
          <div class="col-md-12"><label class="form-label">Formula Explanation</label>
            <textarea name="formula_explanation" class="form-control" rows="2"><?= e($content['formula_explanation'] ?? '') ?></textarea></div>
          <div class="col-md-6"><label class="form-label">Benefits</label>
            <textarea name="benefits" class="form-control" rows="2"><?= e($content['benefits'] ?? '') ?></textarea></div>
          <div class="col-md-6"><label class="form-label">Common Mistakes</label>
            <textarea name="common_mistakes" class="form-control" rows="2"><?= e($content['common_mistakes'] ?? '') ?></textarea></div>
          <div class="col-md-6"><label class="form-label">Tips</label>
            <textarea name="tips" class="form-control" rows="2"><?= e($content['tips'] ?? '') ?></textarea></div>
          <div class="col-md-6"><label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?= e($content['notes'] ?? '') ?></textarea></div>
          <div class="col-md-12"><label class="form-label">Disclaimer override (optional — falls back to the global default)</label>
            <input type="text" name="disclaimer" class="form-control" value="<?= e($content['disclaimer'] ?? '') ?>"></div>
        </div>
      </div>

      <div class="admin-card mb-3">
        <h3 class="h6 fw-bold">Example Calculations</h3>
        <div data-repeater-container="examples">
          <?php foreach ($examples ?: [['title'=>'','input_summary'=>'','output_summary'=>'']] as $i => $ex): ?>
          <div class="faq-repeater-item" data-repeater-row>
            <div class="row g-2">
              <div class="col-md-3"><input type="text" name="example_title[]" class="form-control" placeholder="Title" value="<?= e($ex['title'] ?? '') ?>"></div>
              <div class="col-md-4"><input type="text" name="example_input[]" class="form-control" placeholder="Input, e.g. Revenue=$10,000" value="<?= e($ex['input_summary'] ?? '') ?>"></div>
              <div class="col-md-4"><input type="text" name="example_output[]" class="form-control" placeholder="Result, e.g. Profit=$3,000" value="<?= e($ex['output_summary'] ?? '') ?>"></div>
              <div class="col-md-1"><button type="button" class="btn btn-outline-danger" data-repeater-remove><i class="bi bi-trash"></i></button></div>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="faq-repeater-item d-none" data-repeater-template>
            <div class="row g-2">
              <div class="col-md-3"><input type="text" name="example_title[]" class="form-control" placeholder="Title"></div>
              <div class="col-md-4"><input type="text" name="example_input[]" class="form-control" placeholder="Input"></div>
              <div class="col-md-4"><input type="text" name="example_output[]" class="form-control" placeholder="Result"></div>
              <div class="col-md-1"><button type="button" class="btn btn-outline-danger" data-repeater-remove><i class="bi bi-trash"></i></button></div>
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-repeater-add="[data-repeater-container=examples]">+ Add Example</button>
      </div>

      <div class="admin-card">
        <h3 class="h6 fw-bold">FAQ</h3>
        <div data-repeater-container="faqs">
          <?php foreach ($faqs ?: [['question'=>'','answer'=>'']] as $i => $faq): ?>
          <div class="faq-repeater-item" data-repeater-row data-repeater-type="faq">
            <div class="row g-2">
              <div class="col-md-5"><input type="text" name="faq_question[]" class="form-control" placeholder="Question" value="<?= e($faq['question'] ?? '') ?>"></div>
              <div class="col-md-6"><textarea name="faq_answer[]" class="form-control" rows="1" placeholder="Answer"><?= e($faq['answer'] ?? '') ?></textarea></div>
              <div class="col-md-1"><button type="button" class="btn btn-outline-danger" data-repeater-remove><i class="bi bi-trash"></i></button></div>
            </div>
          </div>
          <?php endforeach; ?>
          <div class="faq-repeater-item d-none" data-repeater-template data-repeater-type="faq">
            <div class="row g-2">
              <div class="col-md-5"><input type="text" name="faq_question[]" class="form-control" placeholder="Question"></div>
              <div class="col-md-6"><textarea name="faq_answer[]" class="form-control" rows="1" placeholder="Answer"></textarea></div>
              <div class="col-md-1"><button type="button" class="btn btn-outline-danger" data-repeater-remove><i class="bi bi-trash"></i></button></div>
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-repeater-add="[data-repeater-container=faqs]">+ Add FAQ</button>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-seo">
      <div class="row">
        <div class="col-lg-8">
          <div class="admin-card">
            <div class="row g-3">
              <div class="col-md-8"><label class="form-label">SEO Title</label>
                <input type="text" name="seo_title" class="form-control" data-counter-seo-title value="<?= e($seo['seo_title'] ?? '') ?>"></div>
              <div class="col-md-4"><label class="form-label">Focus Keyword</label>
                <input type="text" name="focus_keyword" class="form-control" value="<?= e($seo['focus_keyword'] ?? '') ?>"></div>
              <div class="col-md-12"><label class="form-label">Meta Description</label>
                <textarea name="meta_description" class="form-control" data-counter-meta-description rows="2"><?= e($seo['meta_description'] ?? '') ?></textarea></div>
              <div class="col-md-12"><label class="form-label">Secondary Keywords (comma separated)</label>
                <input type="text" name="secondary_keywords" class="form-control" value="<?= e($seo['secondary_keywords'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">Canonical URL</label>
                <input type="text" name="canonical_url" class="form-control" value="<?= e($seo['canonical_url'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">Robots</label>
                <input type="text" name="robots" class="form-control" value="<?= e($seo['robots'] ?? 'index,follow') ?>"></div>
              <div class="col-md-6"><label class="form-label">OG Title</label><input type="text" name="og_title" class="form-control" value="<?= e($seo['og_title'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">OG Image URL</label><input type="text" name="og_image" class="form-control" value="<?= e($seo['og_image'] ?? '') ?>"></div>
              <div class="col-md-12"><label class="form-label">OG Description</label><input type="text" name="og_description" class="form-control" value="<?= e($seo['og_description'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">Twitter Title</label><input type="text" name="twitter_title" class="form-control" value="<?= e($seo['twitter_title'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">Twitter Image URL</label><input type="text" name="twitter_image" class="form-control" value="<?= e($seo['twitter_image'] ?? '') ?>"></div>
              <div class="col-md-12"><label class="form-label">Twitter Description</label><input type="text" name="twitter_description" class="form-control" value="<?= e($seo['twitter_description'] ?? '') ?>"></div>
              <div class="col-md-6"><label class="form-label">Schema Type</label>
                <select name="schema_type" class="form-select">
                  <?php foreach (['WebApplication','SoftwareApplication'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($seo['schema_type'] ?? 'WebApplication') === $st ? 'selected' : '' ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="col-md-6"><label class="form-label">Breadcrumb Title</label><input type="text" name="breadcrumb_title" class="form-control" value="<?= e($seo['breadcrumb_title'] ?? '') ?>"></div>
              <div class="col-md-12"><label class="form-label">Image Alt Text</label><input type="text" name="image_alt_text" class="form-control" value="<?= e($seo['image_alt_text'] ?? '') ?>"></div>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div id="tpSeoScoreWidget" class="tp-seo-score-widget">Fill in the fields to see your SEO score.</div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-related">
      <div class="admin-card">
        <label class="form-label">Related Tools (leave empty to auto-fill from the same category)</label>
        <select name="related_tools[]" class="form-select tp-select2" multiple>
          <?php foreach ($allTools as $rt): ?>
            <option value="<?= (int) $rt['id'] ?>" <?= in_array((int) $rt['id'], $relatedIds, true) ? 'selected' : '' ?>><?= e($rt['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="tab-pane fade" id="tab-display">
      <div class="admin-card">
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['published'=>'Published','draft'=>'Draft','disabled'=>'Disabled','scheduled'=>'Scheduled'] as $k=>$v): ?>
                <option value="<?= $k ?>" <?= ($tool['status'] ?? 'draft') === $k ? 'selected' : '' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="col-md-4"><label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="<?= (int) ($tool['sort_order'] ?? 0) ?>"></div>
          <div class="col-md-4 d-flex align-items-end gap-3">
            <div class="form-check"><input type="checkbox" name="is_featured" class="form-check-input" id="isFeatured" <?= !empty($tool['is_featured']) ? 'checked' : '' ?>><label class="form-check-label" for="isFeatured">Featured</label></div>
            <div class="form-check"><input type="checkbox" name="is_popular" class="form-check-input" id="isPopular" <?= !empty($tool['is_popular']) ? 'checked' : '' ?>><label class="form-check-label" for="isPopular">Popular</label></div>
            <div class="form-check"><input type="checkbox" name="is_trending" class="form-check-input" id="isTrending" <?= !empty($tool['is_trending']) ? 'checked' : '' ?>><label class="form-check-label" for="isTrending">Trending</label></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <button class="btn tp-btn-calc mt-3" style="width:auto;">Save Tool</button>
  <?php if ($tool): ?><span class="ms-3 text-muted">Current stored SEO score: <?= (int) $tool['seo_score'] ?>%</span><?php endif; ?>
</form>
</div>
<div class="col-lg-3"></div>
</div>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
