<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_manager();
$cid = require_company_id();

$categoryFilter = get_int('category_id');
$tools = get_tools_for_company($cid, ['category_id' => $categoryFilter ?: null]);
$categories = get_tool_categories();
$limit = check_company_limit($cid, 'tool_limit', 'tool_assignments', "status='active'");

$pageTitle = 'Quality Tools';
$activeMenu = 'tools';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Quality Tool Library</h4><p class="text-muted mb-0 small"><?= count($tools) ?> tools available - assign them to employees to activate on their dashboard</p></div>
  <a href="<?= base_url('manager/tool-assignment.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-check"></i> Assign Tools</a>
</div>

<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="<?= base_url('manager/quality-tools.php') ?>" class="badge rounded-pill text-decoration-none px-3 py-2 <?= !$categoryFilter ? 'bg-primary' : 'bg-secondary-subtle text-secondary' ?>">All</a>
  <?php foreach ($categories as $c): ?>
    <a href="<?= base_url('manager/quality-tools.php?category_id=' . $c['id']) ?>" class="badge rounded-pill text-decoration-none px-3 py-2 <?= $categoryFilter==$c['id'] ? 'bg-primary' : 'bg-secondary-subtle text-secondary' ?>"><?= out($c['name']) ?></a>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <?php foreach ($tools as $t): ?>
  <div class="col-md-4 col-lg-3">
    <div class="tool-tile">
      <div class="tool-icon"><i class="bi <?= out($t['icon'] ?: 'bi-clipboard-check') ?>"></i></div>
      <h6 class="fw-bold mb-1"><?= out($t['name']) ?></h6>
      <p class="small text-muted mb-2" style="min-height:36px;"><?= out(mb_strimwidth($t['description'] ?? '', 0, 80, '...')) ?></p>
      <div class="d-flex justify-content-between align-items-center">
        <span class="badge bg-secondary-subtle text-secondary text-capitalize small"><?= out(str_replace('_',' ',$t['frequency'])) ?></span>
        <a href="<?= base_url('manager/submissions.php?tool_id=' . $t['id']) ?>" class="small">Submissions &rarr;</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$tools): ?><p class="text-muted">No tools found.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
