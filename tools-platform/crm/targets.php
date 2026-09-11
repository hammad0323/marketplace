<?php
require __DIR__ . '/../includes/config.php';
require_business();
$bid = tp_current_business_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $periodType = ($_POST['period_type'] ?? '') === 'daily' ? 'daily' : 'monthly';
    $periodDate = $periodType === 'monthly' ? date('Y-m-01', strtotime($_POST['period_date'] ?? 'now')) : tp_sanitize_text($_POST['period_date'] ?? date('Y-m-d'), 10);
    $amount = (float) tp_sanitize_number($_POST['target_amount'] ?? 0);

    tp_execute(
        'INSERT INTO crm_targets (business_id, period_type, period_date, target_amount) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE target_amount = VALUES(target_amount)',
        'issd',
        [$bid, $periodType, $periodDate, $amount]
    );
    tp_flash_set('success', 'Target saved.');
    header('Location: ' . tp_url('crm/targets.php'));
    exit;
}

$monthlyTargets = tp_query("SELECT * FROM crm_targets WHERE business_id = ? AND period_type='monthly' ORDER BY period_date DESC LIMIT 12", 'i', [$bid]);

// Actual sales achieved for each of those months, for a target-vs-achievement comparison.
$achievements = [];
foreach ($monthlyTargets as $t) {
    $row = tp_query_one(
        "SELECT COALESCE(SUM(amount),0) c FROM crm_ledger_entries WHERE business_id = ? AND entry_type='sale' AND entry_date >= ? AND entry_date < DATE_ADD(?, INTERVAL 1 MONTH)",
        'iss',
        [$bid, $t['period_date'], $t['period_date']]
    );
    $achievements[$t['period_date']] = (float) ($row['c'] ?? 0);
}

$todayTarget = tp_query_one("SELECT target_amount FROM crm_targets WHERE business_id = ? AND period_type='daily' AND period_date = CURDATE()", 'i', [$bid]);
$todaySales = tp_query_one("SELECT COALESCE(SUM(amount),0) c FROM crm_ledger_entries WHERE business_id = ? AND entry_type='sale' AND entry_date = CURDATE()", 'i', [$bid]);

$crmPageTitle = 'Sales Targets';
$crmActive = 'targets';
require __DIR__ . '/includes/crm-header.php';
?>
<div class="row g-3">
  <div class="col-md-5">
    <div class="admin-card mb-3">
      <h3 class="h6 fw-bold mb-3">Set a Target</h3>
      <form method="post">
        <?= tp_csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Period</label>
          <select name="period_type" class="form-select" id="periodType">
            <option value="monthly">Monthly</option>
            <option value="daily">Daily</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Date</label>
          <input type="date" name="period_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="mb-3"><label class="form-label">Target Amount</label><input type="number" step="0.01" min="0" name="target_amount" class="form-control" required></div>
        <button class="tp-btn w-100" style="background:var(--tp-indigo);color:#fff;" type="submit">Save Target</button>
      </form>
    </div>

    <div class="admin-card">
      <h3 class="h6 fw-bold mb-2">Today</h3>
      <?php $todayTargetAmount = (float) ($todayTarget['target_amount'] ?? 0); $todaySalesAmount = (float) ($todaySales['c'] ?? 0); ?>
      <?php if ($todayTargetAmount > 0): $pct = min(100, ($todaySalesAmount / $todayTargetAmount) * 100); ?>
        <div class="small text-muted mb-1"><?= crm_currency_symbol() . number_format($todaySalesAmount, 0) ?> / <?= crm_currency_symbol() . number_format($todayTargetAmount, 0) ?></div>
        <div class="tp-seo-score-bar"><div class="tp-seo-score-fill" style="width:<?= round($pct, 1) ?>%;background:<?= $pct >= 100 ? '#10B981' : '#7C3AED' ?>;"></div></div>
      <?php else: ?>
        <p class="text-muted small mb-0">No target set for today.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-md-7">
    <div class="admin-card">
      <h3 class="h6 fw-bold mb-3">Monthly Target vs Achievement</h3>
      <table class="table table-sm">
        <thead><tr><th>Month</th><th class="text-end">Target</th><th class="text-end">Achieved</th><th class="text-end">%</th></tr></thead>
        <tbody>
          <?php foreach ($monthlyTargets as $t):
            $achieved = $achievements[$t['period_date']] ?? 0;
            $pct = $t['target_amount'] > 0 ? ($achieved / $t['target_amount']) * 100 : 0;
          ?>
          <tr>
            <td><?= date('F Y', strtotime($t['period_date'])) ?></td>
            <td class="text-end"><?= crm_currency_symbol() . number_format((float) $t['target_amount'], 0) ?></td>
            <td class="text-end"><?= crm_currency_symbol() . number_format($achieved, 0) ?></td>
            <td class="text-end"><span class="badge bg-<?= $pct >= 100 ? 'success' : ($pct >= 60 ? 'warning' : 'danger') ?>"><?= round($pct) ?>%</span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$monthlyTargets): ?><tr><td colspan="4" class="text-center text-muted py-3">No monthly targets set yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/crm-footer.php'; ?>
