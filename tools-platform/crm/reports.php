<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

// Top customers + ABC classification by real total-sales value.
$customerValues = tp_query(
    "SELECT cu.id, cu.name, cu.company,
            COALESCE(SUM(CASE WHEN le.entry_type='sale' THEN le.amount ELSE 0 END),0) total_sales,
            MAX(le.entry_date) last_sale_date,
            COUNT(le.id) txn_count
     FROM crm_customers cu
     LEFT JOIN crm_ledger_entries le ON le.customer_id = cu.id AND le.business_id = cu.business_id AND le.entry_type = 'sale'
     WHERE cu.business_id = ?
     GROUP BY cu.id
     HAVING total_sales > 0
     ORDER BY total_sales DESC",
    'i',
    [$bid]
);

$totalValue = array_sum(array_column($customerValues, 'total_sales'));
$cumulative = 0;
$abcClassified = [];
foreach ($customerValues as $c) {
    $pct = $totalValue > 0 ? ($c['total_sales'] / $totalValue) * 100 : 0;
    $cumulative += $pct;
    $class = $cumulative <= 80 ? 'A' : ($cumulative <= 95 ? 'B' : 'C');
    $abcClassified[] = $c + ['pct' => $pct, 'cumulative' => $cumulative, 'class' => $class];
}

// RFM (Recency / Frequency / Monetary) — simple percentile-rank scoring, 1-5.
function crm_rfm_score(array $values, float $value): int
{
    if (!$values) {
        return 3;
    }
    $rank = 0;
    foreach ($values as $v) {
        if ($v <= $value) {
            $rank++;
        }
    }
    $percentile = $rank / count($values);
    return max(1, min(5, (int) ceil($percentile * 5)));
}

$recencyDays = [];
$frequencies = [];
$monetaries = [];
foreach ($customerValues as $c) {
    $recencyDays[] = $c['last_sale_date'] ? (time() - strtotime($c['last_sale_date'])) / 86400 : 9999;
    $frequencies[] = (int) $c['txn_count'];
    $monetaries[] = (float) $c['total_sales'];
}

$rfmRows = [];
foreach ($customerValues as $i => $c) {
    $days = $c['last_sale_date'] ? (time() - strtotime($c['last_sale_date'])) / 86400 : 9999;
    $rScore = 6 - crm_rfm_score($recencyDays, $days); // fewer days = better = higher score
    $fScore = crm_rfm_score($frequencies, (int) $c['txn_count']);
    $mScore = crm_rfm_score($monetaries, (float) $c['total_sales']);
    $rfmRows[] = $c + ['r' => $rScore, 'f' => $fScore, 'm' => $mScore, 'rfm_total' => $rScore + $fScore + $mScore];
}
usort($rfmRows, fn($a, $b) => $b['rfm_total'] <=> $a['rfm_total']);

// Conversion rate + pipeline value from real lead data.
$leadStats = tp_query_one(
    "SELECT COUNT(*) total, SUM(status='won') won, SUM(status NOT IN ('won','lost')) open,
            COALESCE(SUM(CASE WHEN status NOT IN ('won','lost') THEN expected_value ELSE 0 END),0) pipeline_value
     FROM crm_leads WHERE business_id = ?",
    'i',
    [$bid]
);
$conversionRate = $leadStats['total'] > 0 ? ($leadStats['won'] / $leadStats['total']) * 100 : 0;

// Inactive customers — no sale in 60+ days (or never), for re-engagement.
$inactiveCustomers = tp_query(
    "SELECT cu.id, cu.name, cu.phone, MAX(le.entry_date) last_sale
     FROM crm_customers cu
     LEFT JOIN crm_ledger_entries le ON le.customer_id = cu.id AND le.business_id = cu.business_id AND le.entry_type='sale'
     WHERE cu.business_id = ?
     GROUP BY cu.id
     HAVING MAX(le.entry_date) IS NULL OR MAX(le.entry_date) < DATE_SUB(CURDATE(), INTERVAL 60 DAY)
     ORDER BY MAX(le.entry_date) IS NULL DESC, MAX(le.entry_date) ASC
     LIMIT 20",
    'i',
    [$bid]
);

$crmPageTitle = 'Reports';
$crmActive = 'reports';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= (int) $leadStats['total'] ?></div><div class="stat-label">Total Leads</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= round($conversionRate, 1) ?>%</div><div class="stat-label">Conversion Rate</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= (int) $leadStats['open'] ?></div><div class="stat-label">Open Deals</div></div></div>
  <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-value"><?= crm_currency_symbol() . number_format((float) $leadStats['pipeline_value'], 0) ?></div><div class="stat-label">Pipeline Value</div></div></div>
</div>

<div class="admin-card mb-4">
  <h3 class="h6 fw-bold mb-3">ABC Customer Analysis <span class="small text-muted fw-normal">(by real total sales value)</span></h3>
  <?php if (!$abcClassified): ?>
    <p class="text-muted small mb-0">No sales recorded yet — record a sale or invoice a customer to see this analysis.</p>
  <?php else: ?>
    <table class="table table-sm tp-datatable">
      <thead><tr><th>Customer</th><th class="text-end">Total Sales</th><th class="text-end">% of Total</th><th class="text-end">Cumulative %</th><th class="text-center">Class</th></tr></thead>
      <tbody>
        <?php foreach ($abcClassified as $c):
          $badgeClass = $c['class'] === 'A' ? 'tp-badge-featured' : ($c['class'] === 'B' ? 'tp-badge-popular' : 'tp-badge-trending');
        ?>
        <tr>
          <td><a href="<?= tp_url('crm/customer-view.php?id=' . $c['id']) ?>"><?= e($c['name']) ?></a></td>
          <td class="text-end"><?= crm_currency_symbol() . number_format((float) $c['total_sales'], 0) ?></td>
          <td class="text-end"><?= round($c['pct'], 1) ?>%</td>
          <td class="text-end"><?= round($c['cumulative'], 1) ?>%</td>
          <td class="text-center"><span class="tp-badge <?= $badgeClass ?>"><?= $c['class'] ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card mb-4">
  <h3 class="h6 fw-bold mb-3">RFM Customer Analysis <span class="small text-muted fw-normal">(Recency / Frequency / Monetary, scored 1–5)</span></h3>
  <?php if (!$rfmRows): ?>
    <p class="text-muted small mb-0">No sales recorded yet.</p>
  <?php else: ?>
    <table class="table table-sm">
      <thead><tr><th>Customer</th><th class="text-center">Recency</th><th class="text-center">Frequency</th><th class="text-center">Monetary</th><th class="text-center">Total</th></tr></thead>
      <tbody>
        <?php foreach (array_slice($rfmRows, 0, 15) as $c): ?>
        <tr>
          <td><a href="<?= tp_url('crm/customer-view.php?id=' . $c['id']) ?>"><?= e($c['name']) ?></a></td>
          <td class="text-center"><?= $c['r'] ?></td>
          <td class="text-center"><?= $c['f'] ?></td>
          <td class="text-center"><?= $c['m'] ?></td>
          <td class="text-center fw-bold"><?= $c['rfm_total'] ?>/15</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h3 class="h6 fw-bold mb-3">Inactive Customers <span class="small text-muted fw-normal">(no sale in 60+ days, or never)</span></h3>
  <?php if (!$inactiveCustomers): ?>
    <p class="text-muted small mb-0">No inactive customers — everyone has recent activity.</p>
  <?php else: ?>
    <?php foreach ($inactiveCustomers as $c): ?>
      <div class="d-flex justify-content-between border-bottom py-2 small">
        <span><a href="<?= tp_url('crm/customer-view.php?id=' . $c['id']) ?>"><?= e($c['name']) ?></a></span>
        <span class="text-muted"><?= $c['last_sale'] ? 'Last sale: ' . date('M j, Y', strtotime($c['last_sale'])) : 'No sales yet' ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
