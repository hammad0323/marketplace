<?php
/**
 * PROPOSAL EDITOR — the visual builder.
 *
 * This file only prints the empty editor screen and gives the proposal data
 * to JavaScript (window.PMED). Everything interactive is in assets/js/editor.js,
 * which talks to ajax/editor.php to render and save.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/partials.php';
require_login();

$id = (int) ($_GET['id'] ?? 0);
$p = pm_get($id);
if (!$p) {
    flash('danger', 'Proposal not found.');
    redirect('proposals.php');
}
$pages = pm_pages($id);
$tpl = pm_template((int) $p['template_id']);
$state = [
    'proposal' => [
        'id' => (int) $p['id'], 'title' => $p['title'], 'proposal_number' => $p['proposal_number'], 'status' => $p['status'],
        'client_name' => $p['client_name'], 'client_company' => $p['client_company'], 'client_email' => $p['client_email'],
        'client_phone' => $p['client_phone'], 'client_address' => $p['client_address'], 'client_website' => $p['client_website'],
        'template_id' => (int) $tpl['id'], 'colors' => pm_resolve_colors($p['colors'], $tpl),
        'font_heading' => valid_font($p['font_heading'], $tpl['fonts']['heading']), 'font_body' => valid_font($p['font_body'], $tpl['fonts']['body']),
        'settings' => pm_merge_settings($p['settings']), 'currency' => $p['currency'], 'currency_symbol' => $p['currency_symbol'],
        'proposal_date' => $p['proposal_date'], 'expiry_date' => $p['expiry_date'], 'public_token' => $p['public_token'],
        'total' => (float) $p['total'],
    ],
    'pages' => $pages,
];
// colour presets for the Design panel
$presets = [];
foreach (db_all('SELECT * FROM brand_presets ORDER BY name') as $row) {
    $presets[] = ['id' => (int) $row['id'], 'name' => $row['name'], 'colors' => jdecode($row['colors'])];
}
$templatesMeta = [];
foreach (pm_templates() as $tid => $t) {
    $templatesMeta[$tid] = ['name' => $t['name'], 'colors' => $t['colors'], 'fonts' => $t['fonts'], 'body' => $t['body']];
}
$boot = [
    'state' => $state,
    'blocks' => pm_blocks(),
    'styleDefaults' => pm_style_defaults(),
    'templates' => $templatesMeta,
    'colorKeys' => color_keys(),
    'fonts' => array_keys(font_list()),
    'icons' => icon_names(),
    'currencies' => currencies(),
    'presets' => $presets,
    'publicUrl' => public_url($p['public_token']),
    'company' => ['name' => company()['company_name'], 'logo' => media_src(company()['logo'] ?? '')],
    'vars' => ['client_name', 'client_company', 'client_email', 'client_phone', 'client_address', 'client_website', 'proposal_title', 'project_name', 'proposal_number', 'proposal_date', 'expiry_date', 'company_name', 'company_email', 'company_phone', 'company_whatsapp', 'company_website', 'company_address', 'company_tagline', 'total_amount', 'subtotal', 'currency', 'today', 'proposal_link'],
];

$noLayout = true;
$bodyClass = 'editor-body';
$pageTitle = 'Edit: ' . $p['title'];
$extraHead = proposal_head_assets()
    . '<link href="' . asset('vendor/summernote/summernote-bs5.min.css') . '" rel="stylesheet">'
    . '<link href="' . asset('css/editor.css') . '" rel="stylesheet">';
include __DIR__ . '/layouts/header.php';
$groups = [];
foreach (pm_blocks() as $type => $b) {
    if ($b['group'] === 'hidden') continue;
    $groups[$b['group']][$type] = $b;
}
?>
<div class="ed">
  <header class="ed-top">
    <a href="<?= url('proposals.php') ?>" class="btn btn-icon" title="Back to proposals"><i class="bi bi-arrow-left"></i></a>
    <div class="ed-title">
      <div class="t" id="edTitle"><?= e($p['title']) ?></div>
      <div class="s"><span id="edNumber"><?= e($p['proposal_number']) ?></span> · <?= status_badge($p['status']) ?> · <span id="saveState" class="save-state"><i class="bi bi-cloud-check"></i> Saved</span></div>
    </div>
    <div class="ed-tools">
      <div class="btn-group zoom-ctl">
        <button class="btn btn-light btn-sm" id="zoomOut" title="Zoom out"><i class="bi bi-dash-lg"></i></button>
        <button class="btn btn-light btn-sm" id="zoomVal" title="Fit to screen">100%</button>
        <button class="btn btn-light btn-sm" id="zoomIn" title="Zoom in"><i class="bi bi-plus-lg"></i></button>
      </div>
      <div class="btn-group">
        <button class="btn btn-light btn-sm device-btn" data-w="1280" title="Desktop preview"><i class="bi bi-display"></i></button>
        <button class="btn btn-light btn-sm device-btn" data-w="820" title="Tablet preview"><i class="bi bi-tablet"></i></button>
        <button class="btn btn-light btn-sm device-btn" data-w="390" title="Mobile preview"><i class="bi bi-phone"></i></button>
      </div>
      <button class="btn btn-light btn-sm" id="btnPreview"><i class="bi bi-play-circle"></i> <span class="d-none d-xl-inline">Preview</span></button>
      <button class="btn btn-light btn-sm" id="btnPdf"><i class="bi bi-file-earmark-pdf"></i> <span class="d-none d-xl-inline">PDF</span></button>
      <button class="btn btn-light btn-sm" id="btnShare"><i class="bi bi-share"></i> <span class="d-none d-xl-inline">Share</span></button>
      <div class="dropdown">
        <button class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
          <li><a class="dropdown-item" href="#" id="btnVersions"><i class="bi bi-clock-history me-2"></i>Version history</a></li>
          <li><a class="dropdown-item" href="#" id="btnSaveVersion"><i class="bi bi-bookmark-plus me-2"></i>Save named version</a></li>
          <li><a class="dropdown-item" href="#" id="btnSaveTemplate"><i class="bi bi-collection me-2"></i>Save as pre-built proposal</a></li>
          <li><a class="dropdown-item" href="#" id="btnPrint"><i class="bi bi-printer me-2"></i>Print</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= url('proposal.php?id=' . $id) ?>"><i class="bi bi-info-circle me-2"></i>Proposal details & tracking</a></li>
          <li><a class="dropdown-item" href="#" data-act="duplicate" data-id="<?= $id ?>"><i class="bi bi-copy me-2"></i>Duplicate proposal</a></li>
        </ul>
      </div>
      <button class="btn btn-brand btn-sm px-3" id="btnSave"><i class="bi bi-check2"></i> Save</button>
    </div>
  </header>

  <div class="ed-main">
    <!-- LEFT -->
    <aside class="ed-left">
      <ul class="nav ed-tabs" role="tablist">
        <li><a class="active" data-bs-toggle="tab" href="#tWidgets"><i class="bi bi-grid"></i><span>Widgets</span></a></li>
        <li><a data-bs-toggle="tab" href="#tPages"><i class="bi bi-files"></i><span>Pages</span></a></li>
        <li><a data-bs-toggle="tab" href="#tLibrary" id="libTab"><i class="bi bi-bookmark-star"></i><span>Library</span></a></li>
        <li><a data-bs-toggle="tab" href="#tVars"><i class="bi bi-braces"></i><span>Variables</span></a></li>
      </ul>
      <div class="tab-content ed-scroll">
        <div class="tab-pane fade show active" id="tWidgets">
          <p class="ed-hint">Drag a widget onto a page, or click to add it below the selected block.</p>
          <?php foreach ($groups as $g => $items): ?>
            <div class="wg-group"><?= e($g) ?></div>
            <div class="wg-grid">
              <?php foreach ($items as $type => $b): ?>
                <div class="wg-item" data-type="<?= e($type) ?>"><i class="bi <?= e($b['icon']) ?>"></i><span><?= e($b['label']) ?></span></div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="tab-pane fade" id="tPages">
          <div class="d-flex gap-2 mb-3">
            <button class="btn btn-brand btn-sm flex-fill" id="addPage"><i class="bi bi-plus-lg"></i> Add page</button>
          </div>
          <ul class="pg-list" id="pageList"></ul>
          <p class="ed-hint mt-2"><i class="bi bi-arrows-move"></i> Drag pages to reorder them.</p>
        </div>
        <div class="tab-pane fade" id="tLibrary">
          <div class="d-grid gap-2 mb-3">
            <button class="btn btn-light btn-sm border" id="saveBlockLib"><i class="bi bi-bookmark-plus"></i> Save selected block</button>
            <button class="btn btn-light btn-sm border" id="savePageLib"><i class="bi bi-journal-plus"></i> Save current page</button>
          </div>
          <input class="form-control form-control-sm mb-2" id="libSearch" placeholder="Search saved sections…">
          <div id="libList"><div class="text-muted small">Loading…</div></div>
        </div>
        <div class="tab-pane fade" id="tVars">
          <p class="ed-hint">Click to copy. Variables are replaced automatically in the preview, PDF and public link.</p>
          <div id="varList"></div>
        </div>
      </div>
    </aside>

    <!-- CANVAS -->
    <section class="ed-canvas" id="canvas">
      <div class="ed-zoom" id="zoomBox">
        <div id="docRoot"></div>
      </div>
    </section>

    <!-- RIGHT -->
    <aside class="ed-right">
      <ul class="nav ed-tabs" role="tablist">
        <li><a class="active" data-bs-toggle="tab" href="#tBlock" id="blockTab"><i class="bi bi-sliders"></i><span>Block</span></a></li>
        <li><a data-bs-toggle="tab" href="#tDesign"><i class="bi bi-palette"></i><span>Design</span></a></li>
        <li><a data-bs-toggle="tab" href="#tDoc"><i class="bi bi-gear"></i><span>Document</span></a></li>
      </ul>
      <div class="tab-content ed-scroll">
        <div class="tab-pane fade show active" id="tBlock"><div id="blockForm"><div class="ed-empty"><i class="bi bi-hand-index"></i>Select a block on the page to edit its content and style.</div></div></div>
        <div class="tab-pane fade" id="tDesign"><div id="designForm"></div></div>
        <div class="tab-pane fade" id="tDoc"><div id="docForm"></div></div>
      </div>
    </aside>
  </div>
</div>

<!-- Icon picker -->
<div class="modal fade" id="iconModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Choose an icon</h5><input class="form-control form-control-sm ms-3" style="max-width:240px" id="iconSearch" placeholder="Search icons…"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="icon-grid" id="iconGrid"></div></div>
</div></div></div>

<!-- Media library -->
<div class="modal fade" id="mediaModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Media library</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body"><div class="media-grid" id="mediaGrid"></div></div>
</div></div></div>

<!-- Device preview -->
<div class="modal fade" id="deviceModal" tabindex="-1"><div class="modal-dialog modal-fullscreen"><div class="modal-content bg-dark">
  <div class="modal-header border-0 text-white"><h6 class="modal-title text-white" id="deviceTitle">Preview</h6>
    <div class="btn-group ms-3"><button class="btn btn-sm btn-outline-light device-btn" data-w="1280"><i class="bi bi-display"></i></button><button class="btn btn-sm btn-outline-light device-btn" data-w="820"><i class="bi bi-tablet"></i></button><button class="btn btn-sm btn-outline-light device-btn" data-w="390"><i class="bi bi-phone"></i></button></div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
  <div class="modal-body d-flex justify-content-center p-3"><div class="device-frame" id="deviceFrame"><iframe id="deviceIframe" title="Device preview"></iframe></div></div>
</div></div></div>

<input type="file" id="fileInput" accept="image/*" class="d-none">
<script>window.PMED = <?= json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php
$extraScripts = '<script src="' . asset('vendor/sortablejs/Sortable.min.js') . '"></script>'
    . '<script src="' . asset('vendor/summernote/summernote-bs5.min.js') . '"></script>'
    . '<script src="' . asset('js/editor.js') . '"></script>';
include __DIR__ . '/layouts/footer.php';
