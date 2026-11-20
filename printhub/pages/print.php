<?php
/** Print ordering flow: service → files (crop / page management) → options → preview → checkout. Same API as the app. */
require __DIR__ . '/_layout.php';
$shop = shop_by_code((string) $params['code']);
if (!$shop || $shop['status'] === 'deactivated') {
    redirect('');
}
$session = customer_session(true);
db_exec('UPDATE customer_sessions SET last_shop_id = ? WHERE id = ?', [(int) $shop['id'], (int) $session['id']]);
$p = shop_public($shop);
if (!$p['is_open']) {
    flash('info', $p['status_message']);
    redirect('shop/' . $shop['shop_code']);
}
$cvToken = preg_match('/^[a-f0-9]{32}$/', (string) ($_GET['cv'] ?? '')) ? $_GET['cv'] : null;
$cv = $cvToken ? cv_by_token($cvToken, $session) : null;
$cfg = [
    'shop' => $p, 'categories' => shop_catalog((int) $shop['id']), 'preselect' => (int) ($_GET['category'] ?? 0),
    'cv' => $cv ? ['token' => $cv['public_token'], 'title' => $cv['title'], 'pages' => cv_page_count($cv)] : null,
    'customer' => $session['customer_id'] ? db_row('SELECT name, phone FROM customers WHERE id = ?', [(int) $session['customer_id']]) : null,
    'privacy' => setting('privacy_notice'), 'pdfjs' => asset('vendor/pdfjs/pdf.min.mjs'), 'pdfworker' => path('assets/vendor/pdfjs/pdf.worker.min.mjs'),
];
$modules = '<script type="module">import * as pdfjsLib from ' . json_encode($cfg['pdfjs']) . '; pdfjsLib.GlobalWorkerOptions.workerSrc = ' . json_encode($cfg['pdfworker']) . '; window.pdfjsLib = pdfjsLib; document.dispatchEvent(new Event("pdfjs:ready"));</script>';
c_header('Print at ' . $shop['name'], ['shop' => $shop, 'theme' => $p['theme_color'], 'back' => path('shop/' . $shop['shop_code']), 'css' => ['vendor/cropper/cropper.min.css'], 'active' => 'print']);
?>
<section class="c-narrow" data-wizard>
    <div class="c-steps" data-steps><span></span><span></span><span></span><span></span><span></span></div>
    <div data-view><div class="c-card c-center c-muted"><span class="spinner-border spinner-border-sm"></span> Loading…</div></div>
</section>

<div class="modal fade" id="imgEditor" tabindex="-1" aria-label="Edit image"><div class="modal-dialog modal-lg modal-fullscreen-sm-down modal-dialog-centered"><div class="modal-content" style="border-radius:18px">
    <div class="modal-header"><h5 class="modal-title">Crop &amp; adjust</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="c-editor"><img id="cropImg" alt=""></div>
        <div class="c-tools" data-crop-tools>
            <button type="button" data-crop="rotate-left"><i class="bi bi-arrow-counterclockwise"></i> Rotate</button><button type="button" data-crop="rotate-right"><i class="bi bi-arrow-clockwise"></i></button>
            <button type="button" data-crop="flip"><i class="bi bi-symmetry-vertical"></i> Mirror</button>
            <button type="button" data-crop="zoom-in"><i class="bi bi-zoom-in"></i></button><button type="button" data-crop="zoom-out"><i class="bi bi-zoom-out"></i></button>
            <button type="button" data-crop="reset"><i class="bi bi-arrow-repeat"></i> Reset</button>
        </div>
        <div class="c-opt-label">Crop shape</div>
        <div class="c-pills" data-aspects>
            <button type="button" class="c-pill is-active" data-aspect="free">Free</button><button type="button" class="c-pill" data-aspect="paper">Paper</button>
            <button type="button" class="c-pill" data-aspect="0.7778">Passport 35×45</button><button type="button" class="c-pill" data-aspect="1.5852">ID card</button>
            <button type="button" class="c-pill" data-aspect="1">Square</button><button type="button" class="c-pill" data-aspect="custom">Custom…</button>
        </div>
        <div class="row g-2 mt-2" data-custom-aspect hidden><div class="col"><input class="form-control" type="number" min="1" placeholder="Width (mm)" data-cw></div><div class="col"><input class="form-control" type="number" min="1" placeholder="Height (mm)" data-ch></div></div>
        <p class="small c-muted mt-2 mb-0">Your original photo is kept; the shop prints the cropped version.</p>
    </div>
    <div class="modal-footer"><button type="button" class="c-btn c-btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="c-btn" data-crop-save>Use this crop</button></div>
</div></div></div>

<div class="modal fade" id="pageEditor" tabindex="-1" aria-label="Choose pages"><div class="modal-dialog modal-xl modal-fullscreen-md-down modal-dialog-scrollable"><div class="modal-content" style="border-radius:18px">
    <div class="modal-header"><h5 class="modal-title">Choose &amp; arrange pages</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <input class="form-control" style="max-width:220px" data-range placeholder="Pages e.g. 1-3, 5, 8" aria-label="Page range"><button type="button" class="c-btn c-btn-sm c-btn-light" data-range-apply>Apply</button>
            <button type="button" class="c-btn c-btn-sm c-btn-light" data-pages-all>Select all</button><button type="button" class="c-btn c-btn-sm c-btn-light" data-pages-none>None</button>
            <span class="ms-auto small c-muted" data-pages-count></span>
        </div>
        <p class="small c-muted">Tap a page to include or remove it · <i class="bi bi-arrow-clockwise"></i> rotates · drag (or ◀ ▶) to change the order.</p>
        <div class="c-pages" data-pages></div>
    </div>
    <div class="modal-footer"><button type="button" class="c-btn c-btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="c-btn" data-pages-save>Save pages</button></div>
</div></div></div>
<script>window.PH_PRINT = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<?php c_footer(['vendor/cropper/cropper.min.js', 'js/print.js'], ['active' => 'print', 'modules' => $modules]);
