<?php
/**
 * CREATE PROPOSAL — 3 steps: client details → template → pre-built content.
 * When the form is sent, the proposal is created and the editor opens.
 */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/partials.php';
require_login();
if (!(int) db_value('SELECT COUNT(*) FROM service_templates')) pm_seed_defaults();

$co = company();
$services = db_all('SELECT id, name, service, description, icon FROM service_templates ORDER BY is_system DESC, name');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = post('title');
    $number = post('proposal_number') ?: pm_next_number();
    if ($title === '') $errors[] = 'Proposal title is required.';
    if (post('client_name') === '' && post('client_company') === '') $errors[] = 'Enter the client name or company.';
    if (db_value('SELECT id FROM proposals WHERE proposal_number = ?', [$number])) $errors[] = 'Proposal number ' . $number . ' already exists.';
    if (post('client_email') !== '' && !filter_var(post('client_email'), FILTER_VALIDATE_EMAIL)) $errors[] = 'Client email is not valid.';

    if (!$errors) {
        $tplId = (int) post('template_id', 1);
        $tpl = pm_template($tplId);
        $colors = $tpl['colors'];
        if (post('brand_colors') === '1') {
            $colors['primary'] = clean_color($co['primary_color'], $colors['primary']);
            $colors['accent'] = clean_color($co['accent_color'], $colors['accent']);
            $colors['button'] = clean_color($co['button_color'], $colors['button']);
            if (!color_is_dark($colors['background'])) {
                $colors['secondary'] = clean_color($co['secondary_color'], $colors['secondary']);
            }
        }
        $presetId = (int) post('preset_id', 0);
        if ($presetId && ($preset = db_row('SELECT * FROM brand_presets WHERE id = ?', [$presetId]))) {
            $colors = array_merge($colors, array_filter(jdecode($preset['colors']), 'is_hex_color'));
        }
        $svcId = (int) post('service_template', 0);
        $structure = pm_blank_structure();
        $serviceType = null;
        if ($svcId && ($svc = db_row('SELECT * FROM service_templates WHERE id = ?', [$svcId]))) {
            $structure = jdecode($svc['structure']);
            $serviceType = $svc['service'];
        }
        $cur = post('currency', 'USD');
        $sym = currencies()[$cur]['symbol'] ?? '';
        if ($cur === 'CUSTOM') {
            $cur = strtoupper(mb_substr(post('custom_code', 'CUR'), 0, 8)) ?: 'CUR';
            $sym = mb_substr(post('custom_symbol', ''), 0, 8);
        }
        $id = pm_create([
            'title' => $title, 'proposal_number' => $number, 'service_type' => $serviceType,
            'client_name' => post('client_name'), 'client_company' => post('client_company'), 'client_email' => post('client_email'),
            'client_phone' => post('client_phone'), 'client_address' => post('client_address'), 'client_website' => post('client_website'),
            'template_id' => $tpl['id'], 'colors' => $colors,
            'currency' => $cur, 'currency_symbol' => $sym,
            'proposal_date' => post('proposal_date') ?: date('Y-m-d'),
            'expiry_date' => post('expiry_date') ?: null,
        ], pm_structure_instantiate($structure));
        flash('success', 'Proposal created — start customising it below.');
        redirect('editor.php?id=' . $id . '&new=1');
    }
}

$presets = db_all('SELECT * FROM brand_presets ORDER BY is_default DESC, name');
$defaultTpl = (int) ($co['default_template'] ?? 1);
$pageTitle = 'Create Proposal';
$activeNav = 'create';
$extraHead = proposal_head_assets();
include __DIR__ . '/layouts/header.php';
// helper: value of a form field after a failed submit (or the default)
function old_value($key, $default = '')
{
    return e($_POST[$key] ?? $default);
}
?>
<div class="page-head"><div><h1>Create a new proposal</h1><p>Three quick steps — client, design and content.</p></div></div>

<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<div class="steps">
  <div class="st active" data-step="1"><span class="n">1</span> Client information</div>
  <div class="st" data-step="2"><span class="n">2</span> Choose template</div>
  <div class="st" data-step="3"><span class="n">3</span> Pre-built content</div>
</div>

<form method="post" id="createForm">
  <?= csrf_field() ?>
  <input type="hidden" name="template_id" id="templateId" value="<?= (int) ($_POST['template_id'] ?? ($_GET['template'] ?? $defaultTpl)) ?>">
  <input type="hidden" name="service_template" id="serviceTemplate" value="<?= (int) ($_POST['service_template'] ?? ($_GET['service'] ?? 0)) ?>">

  <!-- STEP 1 -->
  <div class="wstep" data-step="1">
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card-x"><div class="card-head"><h6><i class="bi bi-person-vcard me-1"></i> Client information</h6></div><div class="card-body"><div class="row g-3">
          <div class="col-md-6"><label class="form-label">Client name</label><input class="form-control" name="client_name" value="<?= old_value('client_name') ?>" placeholder="Ahmed Khan"></div>
          <div class="col-md-6"><label class="form-label">Company name</label><input class="form-control" name="client_company" value="<?= old_value('client_company') ?>" placeholder="ABC Company"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="client_email" value="<?= old_value('client_email') ?>"></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="client_phone" value="<?= old_value('client_phone') ?>"></div>
          <div class="col-md-6"><label class="form-label">Website</label><input class="form-control" name="client_website" value="<?= old_value('client_website') ?>" placeholder="www.client.com"></div>
          <div class="col-md-6"><label class="form-label">Address</label><input class="form-control" name="client_address" value="<?= old_value('client_address') ?>"></div>
        </div></div></div>
      </div>
      <div class="col-lg-5">
        <div class="card-x"><div class="card-head"><h6><i class="bi bi-file-earmark-text me-1"></i> Proposal details</h6></div><div class="card-body"><div class="row g-3">
          <div class="col-12"><label class="form-label">Proposal title *</label><input class="form-control" name="title" id="pTitle" required value="<?= old_value('title', 'Website Development Proposal') ?>"></div>
          <div class="col-12"><label class="form-label">Proposal number <span class="text-muted fw-normal small">(auto-generated, editable)</span></label><input class="form-control" name="proposal_number" value="<?= old_value('proposal_number', pm_next_number()) ?>"></div>
          <div class="col-6"><label class="form-label">Proposal date</label><input type="date" class="form-control" name="proposal_date" value="<?= old_value('proposal_date', date('Y-m-d')) ?>"></div>
          <div class="col-6"><label class="form-label">Expiry date</label><input type="date" class="form-control" name="expiry_date" value="<?= old_value('expiry_date', date('Y-m-d', strtotime('+' . (int) ($co['default_validity_days'] ?: 30) . ' days'))) ?>"></div>
          <div class="col-6"><label class="form-label">Currency</label>
            <select class="form-select" name="currency" id="currency">
              <?php foreach (currencies() as $code => $c): ?><option value="<?= $code ?>" <?= ($_POST['currency'] ?? $co['default_currency']) === $code ? 'selected' : '' ?>><?= $code ?> (<?= e(trim($c['symbol'])) ?>)</option><?php endforeach; ?>
              <option value="CUSTOM">Custom…</option>
            </select></div>
          <div class="col-3 custom-cur d-none"><label class="form-label">Code</label><input class="form-control" name="custom_code" placeholder="QAR"></div>
          <div class="col-3 custom-cur d-none"><label class="form-label">Symbol</label><input class="form-control" name="custom_symbol" placeholder="QR "></div>
        </div></div></div>
      </div>
    </div>
    <div class="text-end mt-3"><button type="button" class="btn btn-brand btn-lg px-4 go" data-to="2">Next: Choose template <i class="bi bi-arrow-right"></i></button></div>
  </div>

  <!-- STEP 2 -->
  <div class="wstep d-none" data-step="2">
    <div class="card-x mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
      <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="brand_colors" value="1" id="brandColors"><label class="form-check-label fw-semibold" for="brandColors">Apply my brand colours</label></div>
      <div class="d-flex align-items-center gap-2"><span class="small fw-semibold">or colour preset</span>
        <select name="preset_id" class="form-select form-select-sm" style="width:200px"><option value="0">Template default colours</option>
          <?php foreach ($presets as $pr): ?><option value="<?= (int) $pr['id'] ?>"><?= e($pr['name']) ?></option><?php endforeach; ?></select></div>
      <div class="ms-auto small text-muted"><i class="bi bi-info-circle"></i> Colours, fonts and layout can be changed any time in the editor.</div>
    </div></div>
    <div class="row g-3">
      <?php foreach (pm_templates() as $id => $t): ?>
        <div class="col-xxl-3 col-lg-4 col-md-6">
          <div class="tpl-card" data-id="<?= $id ?>">
            <div class="tpl-thumb"><span class="tpl-check"><i class="bi bi-check-lg"></i></span><?= template_thumb($id) ?></div>
            <div class="tpl-info"><h6><span class="text-muted me-1"><?= sprintf('%02d', $id) ?></span> <?= e($t['name']) ?></h6><p><?= e($t['desc']) ?></p><div class="small mt-2"><span class="chip"><?= e($t['suitable']) ?></span></div></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="d-flex justify-content-between mt-3"><button type="button" class="btn btn-light btn-lg go" data-to="1"><i class="bi bi-arrow-left"></i> Back</button><button type="button" class="btn btn-brand btn-lg px-4 go" data-to="3">Next: Content <i class="bi bi-arrow-right"></i></button></div>
  </div>

  <!-- STEP 3 -->
  <div class="wstep d-none" data-step="3">
    <div class="row g-3">
      <div class="col-xl-3 col-lg-4 col-md-6">
        <div class="svc-card" data-id="0"><div class="ic"><i class="bi bi-file-earmark-plus"></i></div><h6>Blank proposal</h6><p class="small text-muted mb-0">Start with a cover and an introduction page and build the rest yourself.</p></div>
      </div>
      <?php foreach ($services as $s): ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
          <div class="svc-card" data-id="<?= (int) $s['id'] ?>" data-title="<?= e($s['name']) ?>"><div class="ic"><i class="bi bi-<?= e($s['icon'] ?: 'file-earmark-text') ?>"></i></div><h6><?= e($s['name']) ?></h6><p class="small text-muted mb-0"><?= e($s['description']) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="d-flex justify-content-between mt-4"><button type="button" class="btn btn-light btn-lg go" data-to="2"><i class="bi bi-arrow-left"></i> Back</button><button class="btn btn-brand btn-lg px-5"><i class="bi bi-magic"></i> Create & open editor</button></div>
  </div>
</form>
<?php
$extraScripts = <<<'JS'
<script>
$(function () {
  function go(n) {
    if (n > 1) {
      var t = $('#pTitle').val().trim();
      if (!t) { Swal.fire({icon:'warning', title:'Proposal title is required'}); return; }
      if (!$('[name=client_name]').val().trim() && !$('[name=client_company]').val().trim()) { Swal.fire({icon:'warning', title:'Enter the client name or company'}); return; }
    }
    $('.wstep').addClass('d-none').filter('[data-step=' + n + ']').removeClass('d-none');
    $('.steps .st').each(function () { var s = +$(this).data('step'); $(this).toggleClass('active', s === n).toggleClass('done', s < n); });
    window.scrollTo({top: 0, behavior: 'smooth'});
  }
  $('.go').on('click', function () { go(+$(this).data('to')); });
  function selTpl(id) { $('.tpl-card').removeClass('selected').filter('[data-id=' + id + ']').addClass('selected'); $('#templateId').val(id); }
  $('.tpl-card').on('click', function () { selTpl($(this).data('id')); });
  selTpl($('#templateId').val());
  function selSvc(id) { $('.svc-card').removeClass('selected').filter('[data-id=' + id + ']').addClass('selected'); $('#serviceTemplate').val(id); }
  $('.svc-card').on('click', function () {
    selSvc($(this).data('id'));
    var t = $(this).data('title');
    if (t && /^(Website Development Proposal)?$/.test($('#pTitle').val().trim())) $('#pTitle').val(t);
  });
  selSvc($('#serviceTemplate').val());
  $('#currency').on('change', function () { $('.custom-cur').toggleClass('d-none', this.value !== 'CUSTOM'); });
});
</script>
JS;
include __DIR__ . '/layouts/footer.php';
