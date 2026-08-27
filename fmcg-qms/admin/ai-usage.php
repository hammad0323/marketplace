<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_super_admin();

$totalRequests = db_count('ai_logs');
$totalTokens = (int)(db_val("SELECT SUM(tokens_used) FROM ai_logs") ?? 0);
$byFeature = db_all("SELECT feature, COUNT(*) c FROM ai_logs GROUP BY feature ORDER BY c DESC", []);
$byCompany = db_all(
    "SELECT c.name, COUNT(al.id) requests, SUM(al.tokens_used) tokens FROM ai_logs al JOIN companies c ON c.id=al.company_id
     GROUP BY c.id ORDER BY requests DESC LIMIT 20", []
);
$recent = db_all("SELECT al.*, c.name AS company_name, u.name AS user_name FROM ai_logs al
    LEFT JOIN companies c ON c.id=al.company_id LEFT JOIN users u ON u.id=al.user_id
    ORDER BY al.created_at DESC LIMIT 30", []);

$pageTitle = 'AI Usage';
$activeMenu = 'ai-usage';
include __DIR__ . '/../includes/layout_start.php';
?>
<div class="mb-4"><h4 class="fw-bold mb-0">AI Usage</h4><p class="text-muted mb-0 small">Every AI request is logged with company, user and token usage</p></div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-cpu"></i></div><div class="stat-value"><?= $totalRequests ?></div><div class="stat-label">Total AI Requests</div></div></div>
  <div class="col-md-3 col-6"><div class="stat-card"><div class="stat-icon"><i class="bi bi-lightning"></i></div><div class="stat-value"><?= number_format($totalTokens) ?></div><div class="stat-label">Tokens Used</div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Requests by Feature</h3></div>
      <?php foreach ($byFeature as $f): ?>
        <div class="d-flex justify-content-between small py-1 border-bottom"><span class="text-capitalize"><?= out(str_replace('_',' ',$f['feature'])) ?></span><strong><?= $f['c'] ?></strong></div>
      <?php endforeach; ?>
      <?php if (!$byFeature): ?><p class="text-muted small mb-0">No AI activity yet.</p><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="qc-card">
      <div class="qc-card-header"><h3>Top Companies by AI Usage</h3></div>
      <table class="table table-sm mb-0"><thead><tr><th>Company</th><th>Requests</th><th>Tokens</th></tr></thead><tbody>
      <?php foreach ($byCompany as $c): ?><tr><td class="small"><?= out($c['name']) ?></td><td><?= $c['requests'] ?></td><td><?= number_format((int)$c['tokens']) ?></td></tr><?php endforeach; ?>
      <?php if (!$byCompany): ?><tr><td colspan="3" class="text-center text-muted py-3">No AI activity yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>

<div class="qc-card mt-3">
  <div class="qc-card-header"><h3>Recent AI Requests</h3></div>
  <div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Time</th><th>Company</th><th>User</th><th>Feature</th><th>Response</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td class="small text-muted"><?= time_ago($r['created_at']) ?></td>
        <td class="small"><?= out($r['company_name']) ?></td>
        <td class="small"><?= out($r['user_name']) ?></td>
        <td><span class="badge bg-secondary-subtle text-secondary text-capitalize"><?= out(str_replace('_',' ',$r['feature'])) ?></span></td>
        <td class="small text-truncate" style="max-width:400px;"><?= out($r['response']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="5" class="text-center text-muted py-3">No AI activity yet.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php include __DIR__ . '/../includes/layout_end.php'; ?>
