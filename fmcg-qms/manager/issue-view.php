<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$id = get_int('id');
$issue = db_one(
    "SELECT qi.*, d.name AS department_name, p.name AS product_name, b.batch_number, u.name AS assigned_name
     FROM quality_issues qi LEFT JOIN departments d ON d.id=qi.department_id LEFT JOIN products p ON p.id=qi.product_id
     LEFT JOIN batches b ON b.id=qi.batch_id LEFT JOIN users u ON u.id=qi.assigned_to WHERE qi.id=?", [$id]
);
if (!$issue) { flash_set('danger', 'Issue not found.'); redirect(base_url('manager/issues.php')); }
assert_company_owns((int)$issue['company_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = post('form_action');
    if ($action === 'assign') {
        assign_issue($id, $cid, post_int('assigned_to'), current_user_id());
        flash_set('success', 'Issue assigned.');
    } elseif ($action === 'transition') {
        transition_issue_status($id, $cid, post('new_status'), current_user_id(), post('comment', ''));
        flash_set('success', 'Issue status updated.');
    } elseif ($action === 'comment') {
        db_exec("INSERT INTO issue_comments (issue_id, user_id, comment, status_at_comment, created_at) VALUES (?,?,?,?,NOW())",
            [$id, current_user_id(), post('comment'), $issue['status']]);
        flash_set('success', 'Comment added.');
    } elseif ($action === 'create_ncr') {
        $ncrId = create_ncr($cid, current_user_id(), [
            'product_id' => $issue['product_id'], 'batch_id' => $issue['batch_id'], 'department_id' => $issue['department_id'],
            'process' => $issue['defect_type'], 'issue_id' => $id, 'severity' => $issue['severity'], 'description' => $issue['description'],
            'responsible_person' => $issue['assigned_to'], 'due_date' => date('Y-m-d', strtotime('+7 days')),
        ]);
        redirect(base_url('manager/ncr-view.php?id=' . $ncrId));
    } elseif ($action === 'create_capa') {
        $capaId = create_capa($cid, current_user_id(), [
            'source_type' => 'issue', 'source_id' => $id, 'problem_statement' => $issue['description'],
            'responsible_person' => $issue['assigned_to'], 'due_date' => date('Y-m-d', strtotime('+7 days')),
        ]);
        redirect(base_url('manager/capa-view.php?id=' . $capaId));
    }
    redirect(base_url('manager/issue-view.php?id=' . $id));
}

$comments = db_all("SELECT ic.*, u.name AS user_name FROM issue_comments ic LEFT JOIN users u ON u.id=ic.user_id WHERE ic.issue_id=? ORDER BY ic.created_at DESC", [$id]);
$employees = db_all("SELECT id, name FROM users WHERE company_id=? AND status='active' ORDER BY name", [$cid]);
$nextStatus = issue_next_status($issue['status']);
$relatedNcr = db_one("SELECT * FROM ncr WHERE issue_id=?", [$id]);
$relatedCapa = db_one("SELECT * FROM capa WHERE source_type='issue' AND source_id=?", [$id]);

$pageTitle = $issue['issue_number'];
$activeMenu = 'issues';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('manager/issues.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Issues</a>
<div class="d-flex justify-content-between align-items-center mb-4 mt-1">
  <div><h4 class="fw-bold mb-0"><?= out($issue['issue_number']) ?> <?= severity_badge($issue['severity']) ?> <?= status_badge($issue['status']) ?></h4>
  <p class="text-muted mb-0 small"><?= out($issue['department_name']) ?> <?= $issue['product_name'] ? '- ' . out($issue['product_name']) : '' ?> <?= $issue['batch_number'] ? '/ ' . out($issue['batch_number']) : '' ?></p></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Description</h3></div>
      <p class="mb-0"><?= nl2br(out($issue['description'])) ?></p>
    </div>

    <?php if ($issue['ai_recommendation']): ?>
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3><i class="bi bi-robot text-primary"></i> AI Recommendation</h3></div>
      <div class="ai-suggestion-box"><i class="bi bi-stars mt-1"></i><span><?= out($issue['ai_recommendation']) ?></span></div>
    </div>
    <?php endif; ?>

    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Workflow</h3></div>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach (ISSUE_WORKFLOW_STEPS as $step): $done = array_search($step, ISSUE_WORKFLOW_STEPS) <= array_search($issue['status'], ISSUE_WORKFLOW_STEPS); ?>
          <span class="badge <?= $done ? 'bg-primary' : 'bg-secondary-subtle text-secondary' ?>"><?= ucwords(str_replace('_',' ',$step)) ?></span>
        <?php endforeach; ?>
      </div>
      <?php if ($nextStatus): ?>
      <form method="POST" class="d-flex gap-2 align-items-start flex-wrap">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="transition"><input type="hidden" name="new_status" value="<?= $nextStatus ?>">
        <input type="text" name="comment" class="form-control form-control-sm" style="max-width:320px;" placeholder="Optional comment for this transition">
        <button class="btn btn-sm btn-primary">Move to <?= ucwords(str_replace('_',' ',$nextStatus)) ?></button>
      </form>
      <?php else: ?><p class="small text-success mb-0"><i class="bi bi-check-circle-fill"></i> Issue closed.</p><?php endif; ?>
    </div>

    <div class="qc-card">
      <div class="qc-card-header"><h3>Comments</h3></div>
      <form method="POST" class="mb-3 d-flex gap-2">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="comment">
        <input type="text" name="comment" class="form-control form-control-sm" placeholder="Add a comment..." required>
        <button class="btn btn-sm btn-soft-primary">Post</button>
      </form>
      <?php foreach ($comments as $c): ?>
        <div class="border-bottom pb-2 mb-2">
          <div class="small fw-semibold"><?= out($c['user_name'] ?? 'System') ?> <span class="text-muted fw-normal"><?= time_ago($c['created_at']) ?></span></div>
          <div class="small"><?= out($c['comment']) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (!$comments): ?><p class="small text-muted mb-0">No comments yet.</p><?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Assignment</h3></div>
      <form method="POST" class="d-flex gap-2">
        <?= csrf_field() ?><input type="hidden" name="form_action" value="assign">
        <select name="assigned_to" class="form-select form-select-sm">
          <option value="">Unassigned</option>
          <?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>" <?= $issue['assigned_to']==$e['id']?'selected':'' ?>><?= out($e['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-soft-primary">Set</button>
      </form>
    </div>

    <div class="qc-card mb-3">
      <div class="qc-card-header"><h3>Actions</h3></div>
      <?php if ($relatedNcr): ?>
        <a href="<?= base_url('manager/ncr-view.php?id=' . $relatedNcr['id']) ?>" class="btn btn-sm btn-light border w-100 mb-2"><i class="bi bi-file-earmark-excel"></i> View NCR <?= out($relatedNcr['ncr_number']) ?></a>
      <?php else: ?>
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="form_action" value="create_ncr"><button class="btn btn-sm btn-soft-primary w-100 mb-2"><i class="bi bi-file-earmark-excel"></i> Raise NCR</button></form>
      <?php endif; ?>
      <?php if ($relatedCapa): ?>
        <a href="<?= base_url('manager/capa-view.php?id=' . $relatedCapa['id']) ?>" class="btn btn-sm btn-light border w-100"><i class="bi bi-clipboard-data"></i> View CAPA <?= out($relatedCapa['capa_number']) ?></a>
      <?php else: ?>
        <form method="POST"><?= csrf_field() ?><input type="hidden" name="form_action" value="create_capa"><button class="btn btn-sm btn-soft-primary w-100"><i class="bi bi-clipboard-data"></i> Raise CAPA</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
