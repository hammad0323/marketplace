<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/partials.php';
require_login();
pm_refresh_expired();

$id = (int) ($_GET['id'] ?? 0);
$p = pm_get($id);
if (!$p) {
    flash('danger', 'Proposal not found.');
    redirect('proposals.php');
}
$co = company();
$link = public_url($p['public_token']);
$responses = db_all('SELECT * FROM proposal_responses WHERE proposal_id = ? ORDER BY id DESC', [$id]);
$activity = db_all('SELECT a.*, u.name uname FROM proposal_activity a LEFT JOIN users u ON u.id = a.user_id AND a.user_id > 0 WHERE a.proposal_id = ? ORDER BY a.id DESC LIMIT 100', [$id]);
$versions = db_all('SELECT v.id, v.label, v.created_at, u.name FROM proposal_versions v LEFT JOIN users u ON u.id = v.created_by WHERE proposal_id = ? ORDER BY v.id DESC LIMIT 30', [$id]);
$pages = pm_pages($id);
$tpl = pm_template((int) $p['template_id']);
$r = new ProposalRenderer($p, $pages, $co, 'screen');
$defSubject = $co['email_subject'] ?: 'Proposal: {{proposal_title}} — {{company_name}}';
$defBody = $co['email_body'] ?: "Hello {{client_name}},\n\nPlease find our proposal for {{project_name}}.\n\nYou can review the proposal using the link below.\n\n{{proposal_link}}\n\nRegards,\n{{company_name}}";
$actIcons = ['created' => 'bi-plus-circle', 'viewed' => 'bi-eye', 'emailed' => 'bi-envelope', 'accepted' => 'bi-check-circle', 'rejected' => 'bi-x-circle', 'downloaded' => 'bi-download', 'duplicated' => 'bi-copy', 'status_changed' => 'bi-arrow-repeat'];

$pageTitle = $p['title'];
$activeNav = 'proposals';
include __DIR__ . '/layouts/header.php';
?>
<div class="page-head">
  <div>
    <div class="small text-muted mb-1"><a href="<?= url('proposals.php') ?>" class="text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Proposals</a></div>
    <h1><?= e($p['title']) ?></h1>
    <p><?= e($p['proposal_number']) ?> · <?= status_badge($p['status']) ?> · <?= e($tpl['name']) ?> template</p>
  </div>
  <div class="actions">
    <a href="<?= url('editor.php?id=' . $id) ?>" class="btn btn-brand"><i class="bi bi-pencil-square"></i> Edit</a>
    <a href="<?= url('preview.php?id=' . $id) ?>" target="_blank" class="btn btn-light border"><i class="bi bi-play-circle"></i> Preview</a>
    <a href="<?= url('pdf.php?id=' . $id) ?>" class="btn btn-light border"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a href="<?= url('preview.php?id=' . $id . '&print=1') ?>" target="_blank" class="btn btn-light border"><i class="bi bi-printer"></i></a>
    <button class="btn btn-light border" data-act="duplicate" data-id="<?= $id ?>"><i class="bi bi-copy"></i></button>
    <button class="btn btn-light border text-danger" data-act="delete" data-id="<?= $id ?>"><i class="bi bi-trash3"></i></button>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php foreach ([
      ['Total value', money($p['total'], $p['currency_symbol']), 'bi-cash-stack', 'ic-1'],
      ['Views', (int) $p['views'], 'bi-eye', 'ic-4'],
      ['First viewed', $p['first_viewed_at'] ? fmt_date($p['first_viewed_at'], 'M j, H:i') : '—', 'bi-clock', 'ic-3'],
      ['Last viewed', $p['last_viewed_at'] ? time_ago($p['last_viewed_at']) : '—', 'bi-clock-history', 'ic-7'],
  ] as $c): ?>
    <div class="col-md-3 col-6"><div class="card-x stat"><div class="ic <?= $c[3] ?>"><i class="bi <?= $c[2] ?>"></i></div><div><div class="v" style="font-size:20px"><?= e($c[1]) ?></div><div class="l"><?= $c[0] ?></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card-x mb-3" id="send">
      <div class="card-head"><h6><i class="bi bi-send me-1"></i> Send proposal</h6></div>
      <div class="card-body">
        <label class="form-label">Public proposal link</label>
        <div class="input-group mb-3">
          <input class="form-control font-monospace small" readonly value="<?= e($link) ?>" id="pubLink">
          <button class="btn btn-light border" data-act="copy" data-link="<?= e($link) ?>"><i class="bi bi-clipboard"></i> Copy</button>
          <a class="btn btn-light border" href="<?= e($link) ?>" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>
          <button class="btn btn-light border" id="regen" title="Generate a new link (old link stops working)"><i class="bi bi-arrow-repeat"></i></button>
        </div>
        <form id="sendForm">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">To</label><input class="form-control" name="to" type="email" value="<?= e($p['client_email']) ?>" required></div>
            <div class="col-md-6"><label class="form-label">CC <span class="text-muted small">(comma separated)</span></label><input class="form-control" name="cc"></div>
            <div class="col-12"><label class="form-label">Subject</label><input class="form-control" name="subject" value="<?= e($r->replaceVars($defSubject, false)) ?>"></div>
            <div class="col-12"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="8"><?= e($r->replaceVars($defBody, false)) ?></textarea>
              <div class="form-text">The link <code>{{proposal_link}}</code> is shown as a “View Proposal” button.</div></div>
            <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
              <div class="form-check"><input class="form-check-input" type="checkbox" name="attach_pdf" value="1" id="attPdf"><label class="form-check-label" for="attPdf">Attach PDF</label></div>
              <div class="ms-auto d-flex gap-2">
                <a class="btn btn-light border" id="mailtoBtn" href="#"><i class="bi bi-box-arrow-up-right"></i> Open email client</a>
                <button class="btn btn-brand"><i class="bi bi-send"></i> Send email</button>
              </div>
            </div>
          </div>
        </form>
        <?php if (empty($co['smtp_enabled'])): ?><div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-info-circle"></i> SMTP is not configured — emails are sent with PHP mail(). Configure SMTP in <a href="<?= url('settings.php?tab=email') ?>">Settings → Email</a> for reliable delivery.</div><?php endif; ?>
      </div>
    </div>

    <?php if ($responses): ?>
    <div class="card-x mb-3">
      <div class="card-head"><h6><i class="bi bi-chat-square-text me-1"></i> Client response</h6></div>
      <div class="card-body">
        <?php foreach ($responses as $resp): ?>
          <div class="d-flex gap-3 align-items-start mb-3">
            <div class="ic <?= $resp['response'] === 'accepted' ? 'ic-5' : 'ic-6' ?>" style="width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:20px"><i class="bi <?= $resp['response'] === 'accepted' ? 'bi-check-lg' : 'bi-x-lg' ?>"></i></div>
            <div class="flex-fill">
              <div class="fw-bold text-dark"><?= e(ucfirst($resp['response'])) ?> by <?= e($resp['name']) ?> <span class="text-muted fw-normal">&lt;<?= e($resp['email']) ?>&gt;</span></div>
              <div class="small text-muted"><?= e(fmt_date($resp['created_at'], 'F j, Y H:i')) ?> · IP <?= e($resp['ip_address']) ?></div>
              <?php if ($resp['comment']): ?><div class="mt-2 p-3 bg-light rounded"><?= nl2br(e($resp['comment'])) ?></div><?php endif; ?>
              <?php if ($resp['signature_type'] === 'typed'): ?><div class="mt-2" style="font-family:'Playfair Display',serif;font-style:italic;font-size:28px"><?= e($resp['signature_text']) ?></div>
              <?php elseif ($resp['signature_data']): ?><img class="mt-2 border rounded p-2 bg-white" src="<?= e(media_src($resp['signature_data'])) ?>" style="max-height:90px" alt="Signature"><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card-x">
      <div class="card-head"><h6><i class="bi bi-clock-history me-1"></i> Version history</h6></div>
      <div class="table-responsive"><table class="table table-x">
        <tbody>
        <?php if (!$versions): ?><tr><td class="text-muted">No versions yet. Versions are created automatically while editing.</td></tr><?php endif; ?>
        <?php foreach ($versions as $v): ?>
          <tr><td><b class="text-dark"><?= e($v['label']) ?></b><div class="p-sub"><?= e(fmt_date($v['created_at'], 'M j, Y H:i')) ?><?= $v['name'] ? ' · ' . e($v['name']) : '' ?></div></td>
            <td class="text-end"><button class="btn btn-sm btn-light border restore" data-v="<?= (int) $v['id'] ?>"><i class="bi bi-arrow-counterclockwise"></i> Restore</button></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card-x mb-3">
      <div class="card-head"><h6>Status</h6></div>
      <div class="card-body">
        <select class="form-select" id="statusSel">
          <?php foreach (status_meta() as $k => $m): ?><option value="<?= $k ?>" <?= $p['status'] === $k ? 'selected' : '' ?>><?= $m['label'] ?></option><?php endforeach; ?>
        </select>
        <div class="small text-muted mt-2">Status changes automatically when the client opens, accepts or rejects the proposal.</div>
        <hr>
        <dl class="row small mb-0">
          <dt class="col-5 text-muted fw-normal">Created</dt><dd class="col-7"><?= e(fmt_date($p['created_at'], 'M j, Y H:i')) ?></dd>
          <dt class="col-5 text-muted fw-normal">Sent</dt><dd class="col-7"><?= $p['sent_at'] ? e(fmt_date($p['sent_at'], 'M j, Y H:i')) : '—' ?></dd>
          <dt class="col-5 text-muted fw-normal">Proposal date</dt><dd class="col-7"><?= e(fmt_date($p['proposal_date'])) ?></dd>
          <dt class="col-5 text-muted fw-normal">Expires</dt><dd class="col-7"><?= e(fmt_date($p['expiry_date'])) ?: '—' ?></dd>
          <dt class="col-5 text-muted fw-normal">Responded</dt><dd class="col-7 mb-0"><?= $p['responded_at'] ? e(fmt_date($p['responded_at'], 'M j, Y H:i')) : '—' ?></dd>
        </dl>
      </div>
    </div>
    <div class="card-x mb-3">
      <div class="card-head"><h6>Client</h6></div>
      <div class="card-body small">
        <div class="fw-bold text-dark fs-6"><?= e($p['client_name']) ?></div>
        <div class="mb-2"><?= e($p['client_company']) ?></div>
        <?php foreach (['client_email' => 'bi-envelope', 'client_phone' => 'bi-telephone', 'client_website' => 'bi-globe', 'client_address' => 'bi-geo-alt'] as $f => $ic): if ($p[$f]): ?>
          <div class="mb-1"><i class="bi <?= $ic ?> text-muted me-2"></i><?= e($p[$f]) ?></div>
        <?php endif; endforeach; ?>
      </div>
    </div>
    <div class="card-x">
      <div class="card-head"><h6>Activity</h6></div>
      <div class="card-body" style="max-height:520px;overflow:auto">
        <ul class="timeline-x">
          <?php foreach ($activity as $a): ?>
            <li><div class="small fw-semibold text-dark"><i class="bi <?= $actIcons[$a['action']] ?? 'bi-dot' ?>"></i> <?= e(ucfirst(str_replace('_', ' ', $a['action']))) ?></div>
              <div class="small text-muted"><?= e($a['details']) ?></div>
              <div class="small text-muted"><?= e(fmt_date($a['created_at'], 'M j, H:i')) ?><?= $a['uname'] ? ' · ' . e($a['uname']) : '' ?></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
<?php
$extraScripts = '<script>
$(function(){
  var id = ' . $id . ';
  $("#statusSel").on("change", function(){ PM.post("ajax/proposals.php", {action:"status", id:id, status:this.value}).done(function(){ PM.toast("Status updated"); setTimeout(function(){location.reload()}, 600); }).fail(PM.error); });
  $("#sendForm").on("submit", function(e){
    e.preventDefault();
    var d = $(this).serializeArray().reduce(function(o,x){o[x.name]=x.value;return o;}, {action:"send_email", id:id});
    Swal.fire({title:"Sending…", didOpen:function(){Swal.showLoading()}, allowOutsideClick:false});
    PM.post("ajax/proposals.php", d).done(function(){ Swal.fire({icon:"success", title:"Proposal sent!", confirmButtonColor:"#015957"}).then(function(){location.reload()}); }).fail(PM.error);
  });
  $("#mailtoBtn").on("click", function(e){
    e.preventDefault();
    var f = $("#sendForm"); var body = f.find("[name=body]").val().replace("{{proposal_link}}", $("#pubLink").val());
    location.href = "mailto:" + encodeURIComponent(f.find("[name=to]").val()) + "?subject=" + encodeURIComponent(f.find("[name=subject]").val()) + "&body=" + encodeURIComponent(body);
    PM.post("ajax/proposals.php", {action:"status", id:id, status:"' . ($p['status'] === 'draft' ? 'sent' : e($p['status'])) . '"});
  });
  $("#regen").on("click", function(){
    PM.confirm({title:"Generate a new link?", text:"The current public link will stop working."}).then(function(r){ if(!r.isConfirmed) return;
      PM.post("ajax/proposals.php", {action:"regenerate_link", id:id}).done(function(){ location.reload(); }).fail(PM.error); });
  });
  $(".restore").on("click", function(){
    var v = $(this).data("v");
    PM.confirm({title:"Restore this version?", text:"The current content is saved as a version first."}).then(function(r){ if(!r.isConfirmed) return;
      PM.post("ajax/proposals.php", {action:"restore_version", id:id, version_id:v}).done(function(){ PM.toast("Version restored"); setTimeout(function(){location.href = PM.url("editor.php?id=" + id)}, 600); }).fail(PM.error); });
  });
});
</script>';
include __DIR__ . '/layouts/footer.php';
