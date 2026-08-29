<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$cid = require_company_id();
$uid = current_user_id();

$id = get_int('id');
$submission = get_submission_detail($id);
if (!$submission || (int)$submission['company_id'] !== $cid || (int)$submission['user_id'] !== $uid) {
    flash_set('danger', 'Submission not found.');
    redirect(base_url('employee/my-submissions.php'));
}

$linkedIssue = db_one("SELECT * FROM quality_issues WHERE source_type='tool_submission' AND source_id=?", [$id]);

$pageTitle = 'Submission Detail';
$activeMenu = 'my-submissions';
include __DIR__ . '/../includes/layout_start.php';
?>
<a href="<?= base_url('employee/my-submissions.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> My Submissions</a>
<div class="d-flex justify-content-between align-items-center mb-4 mt-1">
  <h4 class="fw-bold mb-0"><?= out($submission['tool_name']) ?></h4>
  <?= status_badge($submission['status']) ?>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Submitted Values</h3></div>
      <table class="table table-sm mb-0">
        <?php foreach ($submission['values'] as $v): ?>
          <tr><td class="text-muted small" style="width:40%;"><?= out($v['label']) ?></td>
              <td><?= out($v['value_number'] !== null ? $v['value_number'] . ' ' . ($v['unit'] ?? '') : $v['value_text']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$submission['values']): ?><tr><td class="text-muted small py-3">No values recorded (missed submission).</td></tr><?php endif; ?>
      </table>
    </div>
  </div>
  <div class="col-lg-4">
    <?php if ($linkedIssue): ?>
    <div class="qc-card">
      <div class="qc-card-header"><h3>Linked Issue</h3></div>
      <div class="fw-semibold small"><?= out($linkedIssue['issue_number']) ?></div>
      <div class="small text-muted mt-1"><?= out($linkedIssue['description']) ?></div>
      <div class="mt-2"><?= severity_badge($linkedIssue['severity']) ?> <?= status_badge($linkedIssue['status']) ?></div>
      <?php if ($linkedIssue['ai_recommendation']): ?>
        <div class="ai-suggestion-box mt-3"><i class="bi bi-stars mt-1"></i><span><?= out($linkedIssue['ai_recommendation']) ?></span></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
