<?php
/** CV maker: list / editor (/cv, /cv/{token}), PDF download (/cv/{token}/pdf), print view (/cv/{token}/preview). */
require __DIR__ . '/_layout.php';
$session = customer_session(true);
$cv = !empty($params['token']) ? cv_by_token($params['token'], $session) : null;
if (!empty($params['token']) && !$cv) {
    flash('info', 'That CV was created on another device or browser.');
    redirect('cv');
}
if ($cv && ($params['view'] ?? null) === 'pdf') {
    if (!rate_limit('cv-pdf:' . $session['id'], 60, 600)) {
        http_response_code(429);
        exit('Please wait a moment.');
    }
    $name = trim(preg_replace('/[^A-Za-z0-9]+/', '-', json_decode($cv['data'], true)['personal']['full_name'] ?? 'cv'), '-') ?: 'cv';
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (isset($_GET['inline']) ? 'inline' : 'attachment') . '; filename="' . $name . '-CV.pdf"');
    header('Cache-Control: private, no-store');
    echo cv_pdf($cv);
    exit;
}
if ($cv && ($params['view'] ?? null) === 'preview') {
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; script-src 'unsafe-inline'");
    $html = cv_render_html($cv);
    if (isset($_GET['print'])) {
        $html = str_replace('</body>', '<script>window.onload = function () { setTimeout(function () { window.print(); }, 300); };</script></body>', $html);
    }
    echo $html;
    exit;
}
$shopCode = shop_by_code((string) ($_GET['shop'] ?? '')) ? strtoupper((string) $_GET['shop']) : null;
if (!$shopCode && $session['last_shop_id'] && ($ls = shop_by_id((int) $session['last_shop_id']))) {
    $shopCode = $ls['shop_code'];
}
$cfg = [
    'templates' => cv_templates_public(), 'sections' => cv_section_labels(), 'default' => cv_default_data(),
    'cv' => $cv ? [
        'token' => $cv['public_token'], 'title' => $cv['title'], 'template_id' => (int) $cv['template_id'], 'accent_color' => $cv['accent_color'],
        'data' => array_replace_recursive(cv_default_data(), json_decode((string) $cv['data'], true) ?: []), 'section_order' => cv_clean_order($cv['section_order']),
        'photo_url' => $cv['photo_path'] ? signed_file_url('cvphoto', (int) $cv['id'], 3600) : null,
    ] : null,
    'list' => $cv ? [] : array_map(fn($r) => ['token' => $r['public_token'], 'title' => $r['title'], 'updated_at' => $r['updated_at'] ?? $r['created_at'],
        'template' => (string) db_val('SELECT name FROM cv_templates WHERE id = ?', [(int) $r['template_id']])],
        db_all('SELECT * FROM customer_cvs WHERE session_id = ? AND deleted_at IS NULL ORDER BY COALESCE(updated_at, created_at) DESC', [(int) $session['id']])),
    'shop' => $shopCode,
];
c_header($cv ? $cv['title'] : 'CV maker', ['description' => 'Create a professional CV in minutes with free templates. Download a PDF or print it at your shop.', 'active' => 'cv',
    'body_class' => 'page-cv', 'back' => $cv ? path('cv') : null]);
?>
<section class="c-wrap" data-cv-app>
<?php if (!$cv): ?>
    <div class="c-narrow px-0">
    <h1 class="c-h1">CV maker</h1><p class="c-muted">Create a professional, ATS-friendly CV — no account needed. Download it as a PDF or send it to a print shop.</p>
    <?php if ($cfg['list']): ?><div class="c-card"><div class="c-card-title"><i class="bi bi-folder2-open"></i> Your CVs on this device</div>
        <?php foreach ($cfg['list'] as $item): ?><div class="d-flex align-items-center gap-2 py-2 border-bottom"><i class="bi bi-file-person fs-4" style="color:var(--c-brand)"></i>
            <a class="flex-grow-1 text-reset" href="<?= e(path('cv/' . $item['token'])) ?>"><strong><?= e($item['title']) ?></strong><br><small class="c-muted"><?= e($item['template']) ?> · <?= e(format_datetime($item['updated_at'])) ?></small></a>
            <button class="c-icon-btn" data-del-cv="<?= e($item['token']) ?>" aria-label="Delete"><i class="bi bi-trash3"></i></button></div><?php endforeach; ?></div><?php endif; ?>
    <h2 class="c-h2 mt-4">Choose a template</h2>
    <div class="cv-tpls" data-new-tpls>
        <?php foreach ($cfg['templates'] as $t): ?><button type="button" class="cv-tpl" data-new="<?= (int) $t['id'] ?>"><div class="cv-tpl-img"><?php if ($t['preview_is_image']): ?><img src="<?= e($t['preview_url']) ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php else: ?><iframe src="<?= e($t['preview_url']) ?>?thumb=1" loading="lazy" title="<?= e($t['name']) ?>" tabindex="-1"></iframe><?php endif; ?></div><?= e($t['name']) ?><?= $t['ats_friendly'] ? '<br><small class="c-muted">ATS-friendly</small>' : '' ?></button><?php endforeach; ?>
    </div>
    </div>
<?php else: ?>
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <input class="form-control fw-bold" style="max-width:320px" data-title value="<?= e($cv['title']) ?>" maxlength="120" aria-label="CV name">
        <span class="small c-muted" data-save-state>Saved</span>
        <div class="cv-mobile-tabs ms-auto"><button class="c-btn c-btn-sm" data-tab="form">Edit</button><button class="c-btn c-btn-sm c-btn-light" data-tab="preview">Preview</button></div>
    </div>
    <div class="cv-layout" data-layout>
        <div class="cv-form-col" data-form></div>
        <div class="cv-preview-col">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <a class="c-btn c-btn-sm" data-pdf href="<?= e(path('cv/' . $cv['public_token'] . '/pdf')) ?>"><i class="bi bi-download"></i> PDF</a>
                <button class="c-btn c-btn-sm c-btn-light" data-print><i class="bi bi-printer"></i> Print</button>
                <button class="c-btn c-btn-sm c-btn-light" data-share><i class="bi bi-share"></i> Share</button>
                <button class="c-btn c-btn-sm c-btn-ghost ms-auto" data-send><i class="bi bi-send"></i> Print at a shop</button>
            </div>
            <div class="cv-preview"><iframe data-preview title="CV preview"></iframe></div>
            <p class="small c-muted mt-2">A4 PDF with real text — readable by applicant tracking systems. The preview updates as you type.</p>
        </div>
    </div>
<?php endif; ?>
</section>
<script>window.PH_CV = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<?php c_footer(['js/cv-builder.js'], ['active' => 'cv']);
