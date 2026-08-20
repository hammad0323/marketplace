<?php
/**
 * tool-page.php — the ONE template every tool URL routes through.
 *
 * The real file on disk (e.g. salary-calculator.php) is a 3-line thin
 * file: it sets $toolSlug and requires this file. .htaccess rewrites
 * the public, extension-less URL (/salary-calculator) to that real
 * file, so visitors and search engines never see ".php". Everything
 * else — breadcrumbs, SEO, schema, related tools, FAQ, layout — is
 * generated from the database. The tool's *own* file
 * (tools/<tool_file>) is included only for its input form +
 * calculation JS; it must NOT output <html>/<head>/nav/footer itself.
 */

require_once __DIR__ . '/config.php';

if (empty($toolSlug)) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$tool = get_tool_by_slug($toolSlug);

if (!$tool || $tool['status'] !== 'published') {
    $redirect = tp_find_redirect('/' . $toolSlug);
    if ($redirect) {
        header('Location: ' . tp_resolve_redirect_target($redirect['new_url']), true, (int) $redirect['redirect_type']);
        exit;
    }
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

track_tool_view((int) $tool['id']);

$full = get_tool_full((int) $tool['id']);
$content = $full['content'] ?? [];
$seoRow = $full['seo'] ?? [];
$category = get_tool_category($tool);
$related = get_related_tools((int) $tool['id'], (int) $tool['category_id'], 6);
$popular = get_popular_tools(4, (int) $tool['id']);

$pageTitle = $seoRow['seo_title'] ?? ($tool['name'] . ' — ' . tp_setting('site_name'));
$pageDescriptionFallback = $seoRow['meta_description'] ?? $tool['short_description'];
$pageSeo = $seoRow ?: [];

$schemaData = [
    'name' => $tool['name'],
    'description' => $tool['short_description'],
    'url' => tp_absolute_url($tool['slug']),
    'category' => $category['name'] ?? '',
    'image' => $tool['featured_image'] ?: null,
];
$appSchemaType = in_array($seoRow['schema_type'] ?? '', ['SoftwareApplication'], true) ? 'SoftwareApplication' : 'WebApplication';
$pageSchemas = [generate_schema($appSchemaType, $schemaData)];

if (!empty($full['faqs'])) {
    $pageSchemas[] = generate_schema('FAQPage', ['faqs' => $full['faqs']]);
}
if (!empty($content['how_to_use']) && substr_count($content['how_to_use'], "\n") >= 1) {
    $steps = array_values(array_filter(array_map('trim', explode("\n", $content['how_to_use']))));
    $pageSchemas[] = generate_schema('HowTo', ['name' => 'How to use ' . $tool['name'], 'steps' => $steps]);
}

$breadcrumbItems = [
    ['label' => 'Home', 'url' => tp_url()],
    ['label' => $category['name'] ?? 'Tools', 'url' => $category ? tp_url($category['slug']) : null],
    ['label' => $seoRow['breadcrumb_title'] ?? $tool['name'], 'url' => null],
];

$bodyClass = 'tool-page';
require __DIR__ . '/header.php';
?>
<div class="tp-container py-4">
  <div class="row g-4">
    <div class="col-lg-5">
      <div class="tp-calc-shell" data-tool-slug="<?= e($tool['slug']) ?>">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="tp-icon" style="width:40px;height:40px;"><i class="bi <?= e($tool['icon'] ?: 'bi-calculator') ?>"></i></span>
          <div>
            <?php if ($tool['is_featured']): ?><span class="tp-badge tp-badge-featured">Featured</span><?php endif; ?>
            <?php if ($tool['is_popular']): ?><span class="tp-badge tp-badge-popular">Popular</span><?php endif; ?>
            <?php if ($tool['is_trending']): ?><span class="tp-badge tp-badge-trending">Trending</span><?php endif; ?>
          </div>
        </div>
        <h1 class="h3 fw-bold mb-1"><?= e($tool['name']) ?></h1>
        <p class="text-muted mb-3"><?= e($tool['short_description']) ?></p>

        <form id="tpCalcForm" onsubmit="return false;" novalidate>
          <?php
            $toolFilePath = TOOLS_PLATFORM_ROOT . '/tools/' . ltrim($tool['tool_file'], '/');
            if (is_file($toolFilePath)) {
                include $toolFilePath;
            } else {
                echo '<div class="alert alert-warning">This tool\'s calculator UI is not yet configured.</div>';
            }
          ?>
        </form>

        <div class="tp-result-box" id="tpResultBox">
          <div class="text-white-50 small text-uppercase" id="tpResultLabel">Result</div>
          <div class="tp-result-value" id="tpResultValue">—</div>
          <div class="tp-result-actions mt-3 d-flex gap-2 flex-wrap">
            <button type="button" data-copy-result><i class="bi bi-clipboard"></i> Copy</button>
            <button type="button" data-print-result><i class="bi bi-printer"></i> Print</button>
            <button type="button" data-share-result><i class="bi bi-share"></i> Share</button>
            <button type="button" data-reset-result><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
          </div>
        </div>
        <div id="tpCalcError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <p class="text-muted small mt-3"><i class="bi bi-shield-check"></i> <?= e($content['disclaimer'] ?? tp_setting('default_disclaimer')) ?></p>
    </div>

    <div class="col-lg-7">
      <?php if (!empty($content['introduction'])): ?>
      <section class="reveal mb-4">
        <h2 class="h5 fw-bold">What is the <?= e($tool['name']) ?>?</h2>
        <div class="text-body"><?= nl2br(e($content['introduction'])) ?></div>
      </section>
      <?php endif; ?>

      <?php if (!empty($content['how_to_use'])): ?>
      <section class="reveal mb-4">
        <h2 class="h5 fw-bold">How to Use This Tool</h2>
        <div class="text-body"><?= nl2br(e($content['how_to_use'])) ?></div>
      </section>
      <?php endif; ?>

      <?php if (!empty($full['formulas']) || !empty($content['formula'])): ?>
      <section class="reveal mb-4">
        <h2 class="h5 fw-bold">Formula</h2>
        <?php if (!empty($content['formula'])): ?>
          <pre class="p-3 rounded-3" style="background:var(--tp-surface-2);white-space:pre-wrap;"><?= e($content['formula']) ?></pre>
        <?php endif; ?>
        <?php foreach ($full['formulas'] as $f): ?>
          <p class="fw-semibold mb-1"><?= e($f['label']) ?></p>
          <pre class="p-3 rounded-3" style="background:var(--tp-surface-2);white-space:pre-wrap;"><?= e($f['formula_text']) ?></pre>
        <?php endforeach; ?>
        <?php if (!empty($content['formula_explanation'])): ?>
          <p class="text-muted"><?= nl2br(e($content['formula_explanation'])) ?></p>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if (!empty($full['examples'])): ?>
      <section class="reveal mb-4">
        <h2 class="h5 fw-bold">Example Calculation</h2>
        <?php foreach ($full['examples'] as $ex): ?>
          <div class="tp-card p-3 mb-2">
            <?php if ($ex['title']): ?><h3 class="h6 fw-bold"><?= e($ex['title']) ?></h3><?php endif; ?>
            <p class="mb-1"><strong>Input:</strong> <?= nl2br(e($ex['input_summary'])) ?></p>
            <p class="mb-0"><strong>Result:</strong> <?= nl2br(e($ex['output_summary'])) ?></p>
          </div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <?php if (!empty($content['benefits']) || !empty($content['tips']) || !empty($content['common_mistakes']) || !empty($content['notes'])): ?>
      <section class="reveal mb-4">
        <?php if (!empty($content['benefits'])): ?><h2 class="h5 fw-bold">Benefits</h2><p><?= nl2br(e($content['benefits'])) ?></p><?php endif; ?>
        <?php if (!empty($content['tips'])): ?><h2 class="h5 fw-bold">Tips</h2><p><?= nl2br(e($content['tips'])) ?></p><?php endif; ?>
        <?php if (!empty($content['common_mistakes'])): ?><h2 class="h5 fw-bold">Common Mistakes</h2><p><?= nl2br(e($content['common_mistakes'])) ?></p><?php endif; ?>
        <?php if (!empty($content['notes'])): ?><h2 class="h5 fw-bold">Notes</h2><p><?= nl2br(e($content['notes'])) ?></p><?php endif; ?>
      </section>
      <?php endif; ?>

      <?php if (!empty($full['faqs'])): ?>
      <section class="reveal mb-4">
        <h2 class="h5 fw-bold mb-3">Frequently Asked Questions</h2>
        <div class="accordion" id="tpFaqAccordion">
          <?php foreach ($full['faqs'] as $i => $faq): ?>
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= (int) $faq['id'] ?>">
                  <?= e($faq['question']) ?>
                </button>
              </h3>
              <div id="faq<?= (int) $faq['id'] ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#tpFaqAccordion">
                <div class="accordion-body"><?= nl2br(e($faq['answer'])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($related): ?>
      <section class="reveal mb-4">
        <h2 class="h5 fw-bold mb-3">Related Tools</h2>
        <div class="row g-3">
          <?php foreach ($related as $r): ?>
            <div class="col-sm-6">
              <a href="<?= tp_url($r['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
                <span class="tp-icon"><i class="bi <?= e($r['icon'] ?: 'bi-calculator') ?>"></i></span>
                <h3><?= e($r['name']) ?></h3>
                <p><?= e($r['short_description']) ?></p>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section class="reveal p-4 rounded-4 text-center" style="background:var(--tp-gradient-accent);color:#fff;">
        <h2 class="h5 fw-bold mb-1">Need another tool?</h2>
        <p class="mb-3">Explore <?= (int) get_tool_count_for_category((int) $tool['category_id']) ?>+ tools in <?= e($category['name'] ?? 'this category') ?>.</p>
        <a href="<?= $category ? tp_url($category['slug']) : tp_url('all-tools') ?>" class="btn btn-light fw-semibold">Browse <?= e($category['name'] ?? 'All Tools') ?></a>
      </section>
    </div>
  </div>
</div>
<script src="<?= tp_asset('js/tp-calculator.js') ?>"></script>
<?php require __DIR__ . '/footer.php'; ?>
