<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_role(['employee']);
$uid = current_user_id();

$assignments = get_assigned_tools_for_user($uid);

$pageTitle = 'Floor Mode';
$activeMenu = 'floor-mode';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4 text-center"><h3 class="fw-bold mb-0">Select a Quality Check</h3></div>
<div class="row g-3">
  <?php foreach ($assignments as $a): ?>
    <div class="col-md-4 col-6">
      <a href="<?= base_url('employee/tool-submit.php?tool_id=' . $a['tool_id'] . '&floor=1') ?>" class="tool-tile text-center py-4">
        <div class="tool-icon mx-auto" style="width:64px;height:64px;font-size:1.8rem;"><i class="bi <?= out($a['icon'] ?: 'bi-clipboard-check') ?>"></i></div>
        <h5 class="fw-bold mt-2 mb-0"><?= out($a['name']) ?></h5>
      </a>
    </div>
  <?php endforeach; ?>
  <?php if (!$assignments): ?><p class="text-muted text-center">No tools assigned yet.</p><?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
