<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$methods = [
    '5whys' => ['slug' => '5-whys', 'label' => '5 Whys', 'icon' => 'bi-question-circle'],
    'fishbone' => ['slug' => 'fishbone-ishikawa', 'label' => 'Fishbone / Ishikawa', 'icon' => 'bi-diagram-2'],
    'fta' => ['slug' => 'fault-tree-analysis', 'label' => 'Fault Tree Analysis', 'icon' => 'bi-diagram-3'],
    '8d' => ['slug' => '8d-problem-solving', 'label' => '8D Problem Solving', 'icon' => 'bi-8-circle'],
    'a3' => ['slug' => 'a3-problem-solving', 'label' => 'A3 Problem Solving', 'icon' => 'bi-file-earmark-text'],
];
$method = get_param('method', '5whys');
if (!isset($methods[$method])) $method = '5whys';
$tool = db_one("SELECT * FROM tools WHERE slug=?", [$methods[$method]['slug']]);
$toolId = $tool['id'] ?? 0;

$submissions = $toolId ? db_all(
    "SELECT ts.*, u.name AS user_name, d.name AS department_name FROM tool_submissions ts
     JOIN users u ON u.id=ts.user_id LEFT JOIN departments d ON d.id=ts.department_id
     WHERE ts.company_id=? AND ts.tool_id=? ORDER BY ts.created_at DESC LIMIT 100", [$cid, $toolId]
) : [];

$viewId = get_int('id');
$viewSubmission = null;
$viewValues = [];
if ($viewId) {
    $viewSubmission = db_one("SELECT ts.*, u.name AS user_name FROM tool_submissions ts JOIN users u ON u.id=ts.user_id WHERE ts.id=? AND ts.company_id=? AND ts.tool_id=?", [$viewId, $cid, $toolId]);
    if ($viewSubmission) {
        $viewValues = get_submission_values_by_field_name($viewId);
    }
}

// FTA is logged as one row per fault-tree branch; group by Top Event for a tree-like view.
$ftaGroups = [];
if ($method === 'fta') {
    foreach ($submissions as $s) {
        $v = get_submission_values_by_field_name($s['id']);
        $top = $v['top_event'] ?? 'Untitled';
        $ftaGroups[$top][] = $v;
    }
}

$pageTitle = 'Root Cause Analysis';
$activeMenu = 'root-cause';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">Root Cause Analysis</h4><p class="text-muted mb-0 small">5 Whys, Fishbone, Fault Tree, 8D and A3 problem-solving records - rendered as structured analyses, not raw form data</p></div>

<div class="d-flex gap-2 mb-3 flex-wrap">
  <?php foreach ($methods as $key => $m): ?>
    <a href="<?= base_url('manager/root-cause.php?method=' . $key) ?>" class="badge rounded-pill text-decoration-none px-3 py-2 <?= $method===$key ? 'bg-primary' : 'bg-secondary-subtle text-secondary' ?>">
      <i class="bi <?= out($m['icon']) ?>"></i> <?= out($m['label']) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if (!$tool): ?>
  <div class="alert alert-warning">This tool isn't in your library yet. Ask your Super Admin to enable it, or load <code>database/tool_library.sql</code>.</div>

<?php elseif ($method === 'fishbone'): ?>
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="qc-card p-2">
        <?php foreach ($submissions as $s): ?>
          <a href="<?= base_url('manager/root-cause.php?method=fishbone&id=' . $s['id']) ?>" class="d-block px-3 py-2 rounded-3 text-decoration-none mb-1 <?= $s['id']==$viewId ? 'bg-primary text-white' : 'text-dark' ?>">
            <div class="small fw-semibold"><?= out(mb_strimwidth(get_submission_values_by_field_name($s['id'])['effect'] ?? 'Fishbone', 0, 40, '...')) ?></div>
            <div class="small <?= $s['id']==$viewId ? 'text-white-50' : 'text-muted' ?>"><?= out($s['user_name']) ?> - <?= time_ago($s['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$submissions): ?><p class="text-muted small p-2">No Fishbone diagrams logged yet.</p><?php endif; ?>
      </div>
    </div>
    <div class="col-lg-8">
      <?php if ($viewSubmission): ?>
        <div class="qc-card">
          <div class="qc-card-header"><h3>Cause &amp; Effect Diagram</h3></div>
          <?= render_fishbone_svg($viewValues['effect'] ?? 'Problem', [
              'Man' => $viewValues['man'] ?? null, 'Machine' => $viewValues['machine'] ?? null, 'Method' => $viewValues['method'] ?? null,
              'Material' => $viewValues['material'] ?? null, 'Measurement' => $viewValues['measurement_cause'] ?? null, 'Environment' => $viewValues['environment'] ?? null,
          ]) ?>
        </div>
      <?php else: ?><p class="text-muted">Select a Fishbone submission to view its diagram.</p><?php endif; ?>
    </div>
  </div>

<?php elseif ($method === '5whys'): ?>
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="qc-card p-2">
        <?php foreach ($submissions as $s): ?>
          <a href="<?= base_url('manager/root-cause.php?method=5whys&id=' . $s['id']) ?>" class="d-block px-3 py-2 rounded-3 text-decoration-none mb-1 <?= $s['id']==$viewId ? 'bg-primary text-white' : 'text-dark' ?>">
            <div class="small fw-semibold"><?= out(mb_strimwidth(get_submission_values_by_field_name($s['id'])['problem_statement'] ?? '5 Whys', 0, 40, '...')) ?></div>
            <div class="small <?= $s['id']==$viewId ? 'text-white-50' : 'text-muted' ?>"><?= out($s['user_name']) ?> - <?= time_ago($s['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$submissions): ?><p class="text-muted small p-2">No 5 Whys analyses logged yet.</p><?php endif; ?>
      </div>
    </div>
    <div class="col-lg-8">
      <?php if ($viewSubmission): ?>
        <div class="qc-card">
          <div class="qc-card-header"><h3>Problem Statement</h3></div>
          <p><?= nl2br(out($viewValues['problem_statement'] ?? '')) ?></p>
          <div class="mt-3">
            <?php foreach (['why_1'=>'Why 1','why_2'=>'Why 2','why_3'=>'Why 3','why_4'=>'Why 4','why_5'=>'Why 5 (Root Cause)'] as $key => $label): ?>
              <?php if (!empty($viewValues[$key])): ?>
                <div class="d-flex gap-3 mb-2">
                  <div class="text-center" style="width:36px;"><span class="badge rounded-circle <?= $key==='why_5' ? 'bg-danger' : 'bg-primary' ?>" style="width:32px;height:32px;line-height:20px;"><?= substr($key, -1) ?></span></div>
                  <div class="flex-grow-1 border-start ps-3 pb-2"><div class="small fw-semibold text-muted"><?= $label ?></div><div><?= out($viewValues[$key]) ?></div></div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      <?php else: ?><p class="text-muted">Select a 5 Whys analysis to view the chain.</p><?php endif; ?>
    </div>
  </div>

<?php elseif ($method === '8d' || $method === 'a3'): ?>
  <?php
  $sections = $method === '8d'
    ? ['d1_team'=>'D1 - Team','d2_problem'=>'D2 - Problem Description','d3_containment'=>'D3 - Containment','d4_root_cause'=>'D4 - Root Cause','d5_corrective'=>'D5 - Corrective Actions','d6_implementation'=>'D6 - Implementation','d7_prevent'=>'D7 - Prevent Recurrence','d8_closure'=>'D8 - Recognition / Closure']
    : ['background'=>'Background','current_condition'=>'Current Condition','goal'=>'Goal','root_cause'=>'Root Cause','countermeasures'=>'Countermeasures','implementation'=>'Implementation Plan','follow_up'=>'Follow-up','results'=>'Results'];
  ?>
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="qc-card p-2">
        <?php foreach ($submissions as $s): ?>
          <a href="<?= base_url('manager/root-cause.php?method=' . $method . '&id=' . $s['id']) ?>" class="d-block px-3 py-2 rounded-3 text-decoration-none mb-1 <?= $s['id']==$viewId ? 'bg-primary text-white' : 'text-dark' ?>">
            <div class="small fw-semibold"><?= out($s['user_name']) ?></div>
            <div class="small <?= $s['id']==$viewId ? 'text-white-50' : 'text-muted' ?>"><?= time_ago($s['created_at']) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$submissions): ?><p class="text-muted small p-2">No <?= out($methods[$method]['label']) ?> records yet.</p><?php endif; ?>
      </div>
    </div>
    <div class="col-lg-8">
      <?php if ($viewSubmission): ?>
        <div class="row g-2">
          <?php foreach ($sections as $key => $label): ?>
            <div class="col-md-6">
              <div class="qc-card h-100">
                <div class="small fw-bold text-primary mb-1"><?= out($label) ?></div>
                <div class="small"><?= nl2br(out($viewValues[$key] ?? '-')) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?><p class="text-muted">Select a record to view its <?= out($methods[$method]['label']) ?> sections.</p><?php endif; ?>
    </div>
  </div>

<?php elseif ($method === 'fta'): ?>
  <div class="qc-card">
    <div class="qc-card-header"><h3>Fault Trees</h3></div>
    <?php foreach ($ftaGroups as $topEvent => $branches): ?>
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2"><i class="bi bi-exclamation-octagon-fill text-danger"></i><strong><?= out($topEvent) ?></strong> <span class="badge bg-danger-subtle text-danger">Top Event</span></div>
        <?php foreach ($branches as $b): ?>
          <div class="ms-4 d-flex align-items-center gap-2 mb-1 small">
            <i class="bi bi-arrow-return-right text-muted"></i>
            <span class="badge <?= ($b['gate_type'] ?? '')==='and' ? 'bg-warning-subtle text-warning' : 'bg-info-subtle text-info' ?> text-uppercase"><?= out($b['gate_type'] ?? 'gate') ?></span>
            <span><?= out($b['intermediate_event'] ?? '') ?></span>
            <?php if (!empty($b['basic_event'])): ?><i class="bi bi-arrow-return-right text-muted ms-2"></i><span class="text-muted">Basic: <?= out($b['basic_event']) ?></span><?php endif; ?>
            <?php if (isset($b['probability']) && $b['probability'] !== ''): ?><span class="badge bg-secondary-subtle text-secondary">p=<?= out($b['probability']) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$ftaGroups): ?><p class="text-muted small mb-0">No Fault Tree Analyses logged yet.</p><?php endif; ?>
  </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
