<?php
require __DIR__ . '/../includes/config.php';
require_business();

$bid = tp_current_business_id();

$stats = [
    'total_customers' => (int) (tp_query_one('SELECT COUNT(*) c FROM crm_customers WHERE business_id = ?', 'i', [$bid])['c'] ?? 0),
    'open_leads' => (int) (tp_query_one("SELECT COUNT(*) c FROM crm_leads WHERE business_id = ? AND status NOT IN ('won','lost')", 'i', [$bid])['c'] ?? 0),
    'pipeline_value' => (float) (tp_query_one("SELECT COALESCE(SUM(expected_value),0) c FROM crm_leads WHERE business_id = ? AND status NOT IN ('won','lost')", 'i', [$bid])['c'] ?? 0),
    'overdue_followups' => (int) (tp_query_one('SELECT COUNT(*) c FROM crm_followups WHERE business_id = ? AND completed_at IS NULL AND due_at < NOW()', 'i', [$bid])['c'] ?? 0),
    'today_followups' => (int) (tp_query_one('SELECT COUNT(*) c FROM crm_followups WHERE business_id = ? AND completed_at IS NULL AND DATE(due_at) = CURDATE()', 'i', [$bid])['c'] ?? 0),
    'total_receivables' => (float) (tp_query_one(
        "SELECT COALESCE(SUM(CASE WHEN entry_type='sale' THEN amount ELSE -amount END),0) c FROM crm_ledger_entries WHERE business_id = ?",
        'i',
        [$bid]
    )['c'] ?? 0),
    'month_sales' => (float) (tp_query_one(
        "SELECT COALESCE(SUM(amount),0) c FROM crm_ledger_entries WHERE business_id = ? AND entry_type='sale' AND entry_date >= DATE_FORMAT(NOW(),'%Y-%m-01')",
        'i',
        [$bid]
    )['c'] ?? 0),
];

$monthTarget = tp_query_one(
    "SELECT target_amount FROM crm_targets WHERE business_id = ? AND period_type='monthly' AND period_date = DATE_FORMAT(NOW(),'%Y-%m-01')",
    'i',
    [$bid]
);
$monthTargetAmount = (float) ($monthTarget['target_amount'] ?? 0);
$targetProgress = $monthTargetAmount > 0 ? min(100, ($stats['month_sales'] / $monthTargetAmount) * 100) : null;

$leadsByStage = tp_query(
    'SELECT status, COUNT(*) c, COALESCE(SUM(expected_value),0) total_value FROM crm_leads WHERE business_id = ? GROUP BY status',
    'i',
    [$bid]
);
$stageMap = [];
foreach ($leadsByStage as $row) {
    $stageMap[$row['status']] = $row;
}

$upcomingFollowups = tp_query(
    "SELECT f.*, l.name lead_name, c.name customer_name FROM crm_followups f
     LEFT JOIN crm_leads l ON l.id = f.lead_id
     LEFT JOIN crm_customers c ON c.id = f.customer_id
     WHERE f.business_id = ? AND f.completed_at IS NULL
     ORDER BY f.due_at ASC LIMIT 6",
    'i',
    [$bid]
);

$topCustomers = tp_query(
    "SELECT cu.id, cu.name, cu.company, COALESCE(SUM(CASE WHEN le.entry_type='sale' THEN le.amount ELSE 0 END),0) total_sales
     FROM crm_customers cu
     LEFT JOIN crm_ledger_entries le ON le.customer_id = cu.id AND le.business_id = cu.business_id
     WHERE cu.business_id = ?
     GROUP BY cu.id ORDER BY total_sales DESC LIMIT 5",
    'i',
    [$bid]
);

$crmPageTitle = 'Dashboard';
$crmActive = 'dashboard';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['Customers', number_format($stats['total_customers']), 'bi-people', '#7C3AED'],
      ['Open Leads', number_format($stats['open_leads']), 'bi-kanban', '#8B5CF6'],
      ['Pipeline Value', tp_format_currency($stats['pipeline_value'], crm_currency_symbol(), 0), 'bi-graph-up', '#5B21B6'],
      ["Today's Follow-ups", number_format($stats['today_followups']), 'bi-bell', '#0EA5E9'],
      ['Overdue Follow-ups', number_format($stats['overdue_followups']), 'bi-exclamation-triangle', '#DC2626'],
      ['Receivables', tp_format_currency($stats['total_receivables'], crm_currency_symbol(), 0), 'bi-cash-coin', '#D97706'],
      ["This Month's Sales", tp_format_currency($stats['month_sales'], crm_currency_symbol(), 0), 'bi-graph-up-arrow', '#10B981'],
  ];
  foreach ($cards as [$label, $value, $icon, $color]):
  ?>
  <div class="col-6 col-md-4 col-lg-3">
    <div class="stat-card">
      <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:1.4rem;"></i>
      <div class="stat-value mt-1"><?= $value ?></div>
      <div class="stat-label"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($targetProgress !== null): ?>
<div class="admin-card mb-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 fw-bold m-0">This Month's Target</h2>
    <span class="small text-muted"><?= tp_format_currency($stats['month_sales'], '', 0) ?> / <?= tp_format_currency($monthTargetAmount, '', 0) ?></span>
  </div>
  <div class="tp-seo-score-bar"><div class="tp-seo-score-fill" style="width:<?= round($targetProgress, 1) ?>%;background:<?= $targetProgress >= 100 ? '#10B981' : '#7C3AED' ?>;"></div></div>
</div>
<?php else: ?>
<div class="admin-card mb-4 d-flex justify-content-between align-items-center">
  <p class="mb-0 text-muted">No monthly sales target set yet.</p>
  <a href="<?= tp_url('crm/targets.php') ?>" class="tp-btn tp-btn-sm" style="background:var(--tp-indigo);color:#fff;">Set a Target</a>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="admin-card">
      <h2 class="h6 fw-bold mb-3">Pipeline by Stage</h2>
      <div class="row g-2">
        <?php foreach (CRM_PIPELINE_STAGES as $key => $label): $s = $stageMap[$key] ?? ['c' => 0, 'total_value' => 0]; ?>
        <div class="col-6 col-md-4">
          <a href="<?= tp_url('crm/leads.php') ?>" class="d-block p-2 rounded-3 text-decoration-none" style="background:#F5F3FF;">
            <div class="small text-muted"><?= e($label) ?></div>
            <div class="fw-bold"><?= (int) $s['c'] ?></div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="admin-card">
      <h2 class="h6 fw-bold mb-3">Top Customers by Sales</h2>
      <?php if (!$topCustomers): ?>
        <p class="text-muted small mb-0">No sales recorded yet.</p>
      <?php else: ?>
        <?php foreach ($topCustomers as $c): ?>
          <div class="d-flex justify-content-between border-bottom py-2 small">
            <span><a href="<?= tp_url('crm/customer-view.php?id=' . $c['id']) ?>"><?= e($c['name']) ?></a><?= $c['company'] ? ' <span class="text-muted">— ' . e($c['company']) . '</span>' : '' ?></span>
            <strong><?= tp_format_currency((float) $c['total_sales'], '', 0) ?></strong>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="admin-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 fw-bold m-0">Upcoming Follow-ups</h2>
    <a href="<?= tp_url('crm/followups.php') ?>" class="small">View all <i class="bi bi-arrow-right"></i></a>
  </div>
  <?php if (!$upcomingFollowups): ?>
    <p class="text-muted small mb-0">No follow-ups scheduled. <a href="<?= tp_url('crm/followups.php') ?>">Add one</a>.</p>
  <?php else: ?>
    <?php foreach ($upcomingFollowups as $f):
      $overdue = strtotime($f['due_at']) < time();
    ?>
      <div class="crm-followup-item <?= $overdue ? 'overdue' : '' ?>">
        <span class="channel-icon"><i class="bi bi-<?= $f['channel'] === 'whatsapp' ? 'whatsapp' : ($f['channel'] === 'meeting' ? 'calendar-event' : ($f['channel'] === 'email' ? 'envelope' : 'telephone')) ?>"></i></span>
        <div class="flex-grow-1">
          <div><?= e($f['note']) ?></div>
          <div class="small text-muted"><?= e($f['lead_name'] ?? $f['customer_name'] ?? '') ?> — due <?= date('M j, g:ia', strtotime($f['due_at'])) ?><?= $overdue ? ' (overdue)' : '' ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/crm-footer.php'; ?>
