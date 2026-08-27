<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$audit = db_one("SELECT a.*, d.name AS department_name FROM audits a LEFT JOIN departments d ON d.id=a.department_id WHERE a.id=? AND a.company_id=?", [$id, $cid]);
if (!$audit) { flash_set('danger', 'Audit not found.'); redirect(base_url('manager/audits.php')); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'score_checklist') {
        $responses = $_POST['response'] ?? [];
        $totalScore = 0; $maxScore = 0;
        foreach ($responses as $itemId => $response) {
            $score = $response === 'conform' ? 5 : ($response === 'minor_nc' ? 3 : ($response === 'major_nc' ? 0 : null));
            db_exec("UPDATE audit_checklists SET response=?, score=?, max_score=5 WHERE id=? AND audit_id=?", [$response, $score, (int)$itemId, $id]);
            if ($response !== 'not_applicable') { $totalScore += $score; $maxScore += 5; }
        }
        $pct = $maxScore > 0 ? round($totalScore / $maxScore * 100, 1) : 0;
        db_exec("UPDATE audits SET score=?, max_score=100, status='completed', completed_date=CURDATE() WHERE id=? AND company_id=?", [$pct, $id, $cid]);
        log_activity($cid, current_user_id(), 'update', 'audit', $id, "Scored audit: $pct%");
        flash_set('success', "Audit scored: $pct%");
    } elseif ($action === 'add_finding') {
        $findingId = db_exec("INSERT INTO audit_findings (audit_id, company_id, finding_text, severity, corrective_action, status, created_at) VALUES (?,?,?,?,?, 'open', NOW())",
            [$id, $cid, post('finding_text'), post('severity','medium'), post('corrective_action')]);
        notify_company_managers($cid, 'audit_finding', 'New Audit Finding', post('finding_text'), base_url('manager/audit-view.php?id=' . $id));
        flash_set('success', 'Finding added.');
    }
    redirect(base_url('manager/audit-view.php?id=' . $id));
}

$checklist = db_all("SELECT * FROM audit_checklists WHERE audit_id=? ORDER BY sort_order", [$id]);
$findings = db_all("SELECT * FROM audit_findings WHERE audit_id=? ORDER BY created_at DESC", [$id]);

$pageTitle = $audit['title'];
$activeMenu = 'audits';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('manager/audits.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Audits</a>
<div class="d-flex justify-content-between align-items-center mb-4 mt-1">
  <h4 class="fw-bold mb-0"><?= out($audit['title']) ?> <?= status_badge($audit['status']) ?></h4>
  <?php if ($audit['score'] !== null): ?><div class="fs-3 fw-bold"><?= $audit['score'] ?>%</div><?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="POST" class="qc-card mb-3">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="score_checklist">
      <h3 class="mb-3">Checklist</h3>
      <?php foreach ($checklist as $item): ?>
        <div class="mb-3 pb-2 border-bottom">
          <div class="small fw-semibold mb-1"><?= out($item['item_text']) ?></div>
          <div class="btn-group btn-group-sm" role="group">
            <?php foreach (['conform'=>'Conform','minor_nc'=>'Minor NC','major_nc'=>'Major NC','not_applicable'=>'N/A'] as $val=>$label): ?>
              <input type="radio" class="btn-check" name="response[<?= $item['id'] ?>]" id="r<?= $item['id'] ?>_<?= $val ?>" value="<?= $val ?>" <?= $item['response']===$val?'checked':'' ?>>
              <label class="btn btn-outline-secondary" for="r<?= $item['id'] ?>_<?= $val ?>"><?= $label ?></label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$checklist): ?><p class="small text-muted">No checklist items configured for this audit.</p><?php endif; ?>
      <?php if ($checklist): ?><button class="btn btn-primary btn-sm">Save & Score</button><?php endif; ?>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Findings</h3></div>
      <form method="POST" class="mb-3">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="add_finding">
        <textarea class="form-control form-control-sm mb-2" name="finding_text" rows="2" placeholder="Finding description" required></textarea>
        <div class="row g-2 mb-2">
          <div class="col-6"><select class="form-select form-select-sm" name="severity"><?php foreach (['critical','high','medium','low','observation'] as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
          <div class="col-6"><input class="form-control form-control-sm" name="corrective_action" placeholder="Corrective action"></div>
        </div>
        <button class="btn btn-sm btn-soft-primary w-100">Add Finding</button>
      </form>
      <?php foreach ($findings as $f): ?>
        <div class="border-bottom py-2">
          <div class="d-flex justify-content-between"><span class="small"><?= out($f['finding_text']) ?></span><?= severity_badge($f['severity']) ?></div>
          <?php if ($f['corrective_action']): ?><div class="small text-muted">Action: <?= out($f['corrective_action']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$findings): ?><p class="small text-muted mb-0">No findings recorded.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
