<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

$leads = tp_query('SELECT * FROM crm_leads WHERE business_id = ? ORDER BY updated_at DESC', 'i', [$bid]);
$byStage = array_fill_keys(array_keys(CRM_PIPELINE_STAGES), []);
foreach ($leads as $lead) {
    $byStage[$lead['status']][] = $lead;
}

$crmPageTitle = 'Sales Pipeline';
$crmActive = 'leads';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <p class="text-muted mb-0 small">Drag a card to move it to a different stage.</p>
  <a href="<?= tp_url('crm/lead-form.php') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;"><i class="bi bi-plus-lg"></i> Add Lead</a>
</div>

<div class="crm-kanban">
  <?php foreach (CRM_PIPELINE_STAGES as $stage => $label):
    $stageLeads = $byStage[$stage];
    $stageValue = array_sum(array_column($stageLeads, 'expected_value'));
  ?>
  <div class="crm-kanban-col" data-status="<?= e($stage) ?>">
    <h3><?= e($label) ?> <span class="count"><?= count($stageLeads) ?></span></h3>
    <?php if ($stageValue > 0): ?><div class="small text-muted px-1 mb-2"><?= crm_currency_symbol() . number_format($stageValue, 0) ?></div><?php endif; ?>
    <div class="crm-kanban-cards">
      <?php foreach ($stageLeads as $lead): ?>
      <div class="crm-lead-card" draggable="true" data-lead-id="<?= (int) $lead['id'] ?>">
        <h4><a href="<?= tp_url('crm/lead-form.php?id=' . $lead['id']) ?>" class="text-decoration-none"><?= e($lead['name']) ?></a></h4>
        <div class="meta"><?= e($lead['company'] ?: $lead['phone'] ?: '') ?></div>
        <?php if ($lead['expected_value'] > 0): ?><div class="value"><?= crm_currency_symbol() . number_format((float) $lead['expected_value'], 0) ?></div><?php endif; ?>
        <?php if ($lead['next_followup_at']): ?>
          <div class="small text-muted mt-1"><i class="bi bi-bell"></i> <?= date('M j', strtotime($lead['next_followup_at'])) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
