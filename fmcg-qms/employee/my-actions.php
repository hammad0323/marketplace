<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$cid = require_company_id();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $actionId = post_int('action_id');
    db_exec("UPDATE capa_actions SET status='done' WHERE id=? AND company_id=? AND responsible_user=?", [$actionId, $cid, $uid]);
    log_activity($cid, $uid, 'update', 'capa_action', $actionId, 'Marked action complete');
    flash_set('success', 'Action marked complete.');
    redirect(base_url('employee/my-actions.php'));
}

$actions = db_all(
    "SELECT ca.*, c.capa_number FROM capa_actions ca JOIN capa c ON c.id=ca.capa_id WHERE ca.company_id=? AND ca.responsible_user=? ORDER BY ca.status, ca.due_date",
    [$cid, $uid]
);
$ncrList = db_all("SELECT * FROM ncr WHERE company_id=? AND responsible_person=? AND status<>'closed' ORDER BY due_date", [$cid, $uid]);
$capaList = db_all("SELECT * FROM capa WHERE company_id=? AND responsible_person=? AND status NOT IN ('closed','rejected','effective') ORDER BY due_date", [$cid, $uid]);

$pageTitle = 'My Actions';
$activeMenu = 'my-actions';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">My Actions</h4></div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>CAPA Action Items</h3></div>
      <?php foreach ($actions as $a): ?>
        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
          <div><div class="small fw-semibold"><?= out($a['capa_number']) ?></div><div class="small text-muted"><?= out($a['action_text']) ?></div>
            <div class="small <?= (strtotime($a['due_date']) < time() && $a['status']!=='done') ? 'text-danger fw-semibold' : 'text-muted' ?>">Due <?= fmt_date($a['due_date']) ?></div></div>
          <?php if ($a['status'] !== 'done'): ?>
            <form method="POST"><?= csrf_field() ?><input type="hidden" name="action_id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-soft-primary">Mark Done</button></form>
          <?php else: ?><?= status_badge('done') ?><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$actions): ?><p class="small text-muted mb-0 mt-2">No action items assigned.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>NCR Assigned to Me</h3></div>
      <?php foreach ($ncrList as $n): ?><div class="d-flex justify-content-between py-2 border-bottom"><span class="small"><?= out($n['ncr_number']) ?></span><?= status_badge($n['status']) ?></div><?php endforeach; ?>
      <?php if (!$ncrList): ?><p class="small text-muted mb-0 mt-2">None.</p><?php endif; ?>
    </div>
    <div class="qc-card">
      <div class="qc-card-header"><h3>CAPA Assigned to Me</h3></div>
      <?php foreach ($capaList as $c): ?><div class="d-flex justify-content-between py-2 border-bottom"><span class="small"><?= out($c['capa_number']) ?></span><?= status_badge($c['status']) ?></div><?php endforeach; ?>
      <?php if (!$capaList): ?><p class="small text-muted mb-0 mt-2">None.</p><?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
