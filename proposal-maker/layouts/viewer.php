<?php
/**
 * PROPOSAL VIEWER — the page your client sees (and the admin preview).
 *
 * Shows all A4 sheets, the Download / Print buttons and, for clients,
 * the Accept / Reject forms with the signature pad.
 */
function render_viewer($p, $pages, $mode, $opt = [])
{
    $co = company();
    $doc = doc_create($p, $pages, $co, 'screen');
    $sheets = render_sheets($doc);
    $settings = $doc['settings'];
    $token = $p['public_token'];
    $embed = !empty($opt['embed']);
    $print = !empty($opt['print']);
    $response = db_row('SELECT * FROM proposal_responses WHERE proposal_id = ? ORDER BY id DESC LIMIT 1', [(int) $p['id']]);
    $expired = $p['status'] === 'expired' || ($p['expiry_date'] && $p['expiry_date'] < date('Y-m-d') && !in_array($p['status'], ['accepted', 'rejected'], true));
    $canRespond = $mode === 'public' && !empty($settings['allow_accept']) && !$response && !$expired;
    $pdfUrl = $mode === 'public' ? url('pdf.php?token=' . $token) : url('pdf.php?id=' . (int) $p['id']);
    $primary = doc_color($doc, 'primary');
    $logo = !empty($co['logo']) ? '<img src="' . e(media_src($co['logo'])) . '" alt="" style="height:30px">' : '<b>' . e($co['company_name']) . '</b>';
    $fonts = font_face_css([$doc['font_heading'], $doc['font_body'], 'Playfair Display', 'Inter']);
    $client = $p['client_company'] ?: $p['client_name'];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($p['title'] . ($client ? ' — ' . $client : '')) ?></title>
<link href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>" rel="stylesheet">
<link href="<?= asset('vendor/sweetalert2/sweetalert2.min.css') ?>" rel="stylesheet">
<?= doc_head_tags($doc) ?>
<style>
<?= $fonts ?>
:root { --vp: <?= e($primary) ?>; }
body { background: #e7ebf1; font-family: Inter, system-ui, sans-serif; color: #334155; }
.vbar { position: sticky; top: 0; z-index: 50; background: rgba(255,255,255,.92); backdrop-filter: blur(10px); border-bottom: 1px solid #e2e8f0; }
.vbar .in { max-width: 1100px; margin: 0 auto; padding: 10px 16px; display: flex; align-items: center; gap: 12px; }
.vbar .tt { min-width: 0; flex: 1; }
.vbar .tt b { display: block; color: #0f172a; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vbar .tt small { color: #64748b; font-size: 12px; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vbar img { max-width: 120px; object-fit: contain; }
@media (max-width: 576px) { .vbar .tt small { display: none; } .vbar .in > b { max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; } }
.btn-vp { background: var(--vp); color: <?= e(doc_on($doc, 'primary')) ?>; border: 0; font-weight: 600; }
.btn-vp:hover { background: var(--vp); color: <?= e(doc_on($doc, 'primary')) ?>; filter: brightness(1.1); }
.sheets { padding: 28px 12px 10px; }
.sheet-wrap { margin: 0 auto 26px; position: relative; }
.sheet-wrap .pm-sheet { transform-origin: top left; margin: 0 !important; }
.vpanel { max-width: 793px; margin: 0 auto 60px; background: #fff; border-radius: 18px; box-shadow: 0 10px 40px rgba(15,23,42,.1); padding: 34px; }
.vpanel h3 { color: #0f172a; font-weight: 700; }
.sig-tabs .nav-link { color: #475569; font-weight: 600; font-size: 13px; }
.sig-tabs .nav-link.active { color: var(--vp); border-color: #dee2e6 #dee2e6 #fff; }
#sigPad { width: 100%; height: 170px; border: 2px dashed #cbd5e1; border-radius: 12px; touch-action: none; background: #fafbfd; cursor: crosshair; }
.typed-sig { font-family: 'Playfair Display', serif; font-style: italic; font-size: 34px; color: #0f172a; min-height: 60px; border-bottom: 2px solid #0f172a; }
.banner { max-width: 793px; margin: 0 auto 20px; border-radius: 14px; padding: 14px 18px; display: flex; gap: 12px; align-items: center; }
.admin-note { background: #0f172a; color: #e2e8f0; font-size: 12.5px; text-align: center; padding: 6px; }
.admin-note a { color: #5eead4; }
.swal2-popup { border-radius: 18px !important; }
<?php if ($embed): ?>.vbar .in { padding: 8px 12px; } .sheets { padding-top: 14px; }<?php endif; ?>
@media print { .vbar, .vpanel, .banner, .admin-note, .no-print { display: none !important; } .sheets { padding: 0 !important; } .sheet-wrap { margin: 0 !important; width: auto !important; height: auto !important; } .sheet-wrap .pm-sheet { transform: none !important; } body { background: #fff !important; } }
</style>
</head>
<body>
<?php if ($mode === 'admin' && !$embed): ?>
<div class="admin-note no-print"><i class="bi bi-eye"></i> Preview mode — this is exactly what your client sees. Views are not tracked. <a href="<?= url('editor.php?id=' . (int) $p['id']) ?>">Back to editor</a></div>
<?php endif; ?>
<div class="vbar no-print"><div class="in">
  <?= $logo ?>
  <div class="tt"><b><?= e($p['title']) ?></b><small><?= e($p['proposal_number']) ?><?= $client ? ' · Prepared for ' . e($client) : '' ?></small></div>
  <?php if (!empty($settings['allow_download']) || $mode === 'admin'): ?>
    <a class="btn btn-light border btn-sm" href="<?= e($pdfUrl) ?>"><i class="bi bi-download"></i><span class="d-none d-md-inline"> PDF</span></a>
  <?php endif; ?>
  <button class="btn btn-light border btn-sm" onclick="window.print()"><i class="bi bi-printer"></i><span class="d-none d-md-inline"> Print</span></button>
  <?php if ($canRespond): ?>
    <a class="btn btn-vp btn-sm px-3" href="#pm-respond"><i class="bi bi-check2-circle"></i> Accept</a>
  <?php endif; ?>
</div></div>

<div class="sheets">
  <?php if ($response): ?>
    <div class="banner no-print" style="background:<?= $response['response'] === 'accepted' ? '#dcfce7;color:#166534' : '#fee2e2;color:#991b1b' ?>">
      <i class="bi <?= $response['response'] === 'accepted' ? 'bi-patch-check-fill' : 'bi-x-octagon-fill' ?> fs-4"></i>
      <div><b>This proposal was <?= e($response['response']) ?></b> by <?= e($response['name']) ?> on <?= e(fmt_date($response['created_at'], 'F j, Y \a\t H:i')) ?>.</div>
    </div>
  <?php elseif ($expired): ?>
    <div class="banner no-print" style="background:#fef3c7;color:#92400e"><i class="bi bi-hourglass-bottom fs-4"></i><div><b>This proposal has expired</b> (valid until <?= e(fmt_date($p['expiry_date'])) ?>). Please contact <?= e($co['company_name']) ?> for an updated proposal.</div></div>
  <?php endif; ?>
  <div <?= doc_attrs($doc) ?>>
    <?php foreach ($sheets as $s): ?><div class="sheet-wrap"><?= $s ?></div><?php endforeach; ?>
  </div>
</div>

<?php if ($mode === 'public' && !$embed && !$print): ?>
<div class="vpanel no-print" id="pm-respond">
  <?php if ($response && $response['response'] === 'accepted'): ?>
    <div class="text-center">
      <div class="display-5 text-success mb-2"><i class="bi bi-patch-check-fill"></i></div>
      <h3>Proposal accepted — thank you!</h3>
      <p class="text-muted">Accepted by <b><?= e($response['name']) ?></b> (<?= e($response['email']) ?>) on <?= e(fmt_date($response['created_at'], 'F j, Y \a\t H:i')) ?>.</p>
      <?php if ($response['signature_type'] === 'typed'): ?><div class="typed-sig d-inline-block px-4"><?= e($response['signature_text']) ?></div>
      <?php elseif ($response['signature_data']): ?><img src="<?= e(media_src($response['signature_data'])) ?>" alt="Signature" style="max-height:110px"><?php endif; ?>
      <p class="small text-muted mt-3"><?= e($co['company_name']) ?> will be in touch shortly with the next steps.</p>
    </div>
  <?php elseif ($response): ?>
    <div class="text-center"><h3>Proposal declined</h3><p class="text-muted">Thank you for letting us know. If anything changes, contact us at <?= e($co['email']) ?>.</p></div>
  <?php elseif ($canRespond): ?>
    <div class="text-center mb-4">
      <h3 class="mb-1">Ready to move forward?</h3>
      <p class="text-muted mb-0">I accept the proposal and terms &amp; conditions.</p>
    </div>
    <div class="d-grid d-sm-flex gap-2 justify-content-center">
      <button class="btn btn-vp btn-lg px-5" data-bs-toggle="modal" data-bs-target="#acceptModal"><i class="bi bi-check2-circle"></i> Accept Proposal</button>
      <button class="btn btn-outline-secondary btn-lg px-4" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="bi bi-x-circle"></i> Reject Proposal</button>
      <?php if (!empty($settings['allow_download'])): ?><a class="btn btn-light border btn-lg" href="<?= e($pdfUrl) ?>"><i class="bi bi-download"></i> Download PDF</a><?php endif; ?>
    </div>
    <p class="text-center small text-muted mt-3 mb-0">Questions? Contact <?= e($co['company_name']) ?><?= $co['email'] ? ' · ' . e($co['email']) : '' ?><?= $co['phone'] ? ' · ' . e($co['phone']) : '' ?></p>
  <?php else: ?>
    <div class="text-center"><h3 class="mb-1">Questions about this proposal?</h3><p class="text-muted mb-0">Contact <?= e($co['company_name']) ?><?= $co['email'] ? ' at ' . e($co['email']) : '' ?>.</p></div>
  <?php endif; ?>
</div>

<?php if ($canRespond): ?>
<div class="modal fade" id="acceptModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content" style="border-radius:18px">
  <form id="acceptForm">
  <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Accept proposal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label fw-semibold small">Full name *</label><input class="form-control" name="name" required value="<?= e($p['client_name']) ?>"></div>
      <div class="col-md-6"><label class="form-label fw-semibold small">Email *</label><input class="form-control" type="email" name="email" required value="<?= e($p['client_email']) ?>"></div>
      <div class="col-12"><label class="form-label fw-semibold small">Comment (optional)</label><textarea class="form-control" name="comment" rows="2"></textarea></div>
      <div class="col-12">
        <label class="form-label fw-semibold small">Signature <?= !empty($settings['require_signature']) ? '*' : '(optional)' ?></label>
        <ul class="nav nav-tabs sig-tabs" role="tablist">
          <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#sgType" data-sig="typed"><i class="bi bi-keyboard"></i> Type</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#sgDraw" data-sig="drawn"><i class="bi bi-pen"></i> Draw</button></li>
          <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#sgUp" data-sig="uploaded"><i class="bi bi-upload"></i> Upload</button></li>
        </ul>
        <div class="tab-content border border-top-0 rounded-bottom p-3">
          <div class="tab-pane fade show active" id="sgType"><input class="form-control mb-2" id="sigText" placeholder="Type your full name"><div class="typed-sig" id="sigTyped"></div></div>
          <div class="tab-pane fade" id="sgDraw"><canvas id="sigPad"></canvas><button type="button" class="btn btn-sm btn-light border mt-2" id="sigClear"><i class="bi bi-eraser"></i> Clear</button></div>
          <div class="tab-pane fade" id="sgUp"><input type="file" class="form-control" id="sigFile" accept="image/png,image/jpeg"><img id="sigPreview" class="mt-2 d-none" style="max-height:90px" alt=""></div>
        </div>
      </div>
      <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="agree" required><label class="form-check-label small" for="agree">I accept the proposal and the terms &amp; conditions, and agree that my electronic signature is legally binding.</label></div></div>
    </div>
  </div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-vp px-4"><i class="bi bi-check2-circle"></i> Accept Proposal</button></div>
  </form>
</div></div></div>

<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:18px">
  <form id="rejectForm">
  <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Reject proposal</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label fw-semibold small">Full name *</label><input class="form-control" name="name" required value="<?= e($p['client_name']) ?>"></div>
    <div class="mb-3"><label class="form-label fw-semibold small">Email *</label><input class="form-control" type="email" name="email" required value="<?= e($p['client_email']) ?>"></div>
    <div><label class="form-label fw-semibold small">Reason for rejecting *</label><textarea class="form-control" name="comment" rows="3" required placeholder="Let us know how we could improve…"></textarea></div>
  </div>
  <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger px-4">Reject Proposal</button></div>
  </form>
</div></div></div>
<?php endif; ?>
<?php endif; ?>

<script src="<?= asset('vendor/jquery/jquery.min.js') ?>"></script>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
<script>
window.PMV = {endpoint: <?= json_encode(url('ajax/public.php')) ?>, token: <?= json_encode($token) ?>, csrf: <?= json_encode(csrf_token()) ?>, requireSig: <?= !empty($settings['require_signature']) ? 'true' : 'false' ?>, color: <?= json_encode($primary) ?>, print: <?= $print ? 'true' : 'false' ?>};
</script>
<script src="<?= asset('js/viewer.js') ?>"></script>
</body>
</html>
<?php
}
