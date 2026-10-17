<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/layouts/partials.php';
require_login();
pm_refresh_expired();
if (!(int) db_value('SELECT COUNT(*) FROM service_templates')) pm_seed_defaults();

$counts = array_fill_keys(array_keys(status_meta()), 0);
foreach (db_all('SELECT status, COUNT(*) c FROM proposals GROUP BY status') as $r) $counts[$r['status']] = (int) $r['c'];
$total = array_sum($counts);
$value = (float) db_value('SELECT COALESCE(SUM(total),0) FROM proposals');
$won = (float) db_value("SELECT COALESCE(SUM(total),0) FROM proposals WHERE status = 'accepted'");
$decided = $counts['accepted'] + $counts['rejected'];
$winRate = $decided ? round($counts['accepted'] / $decided * 100) : 0;
$sym = currencies()[company()['default_currency'] ?? 'USD']['symbol'] ?? '$';

$recent = db_all('SELECT * FROM proposals ORDER BY COALESCE(updated_at, created_at) DESC LIMIT 8');
$activity = db_all('SELECT a.*, p.title, p.proposal_number FROM proposal_activity a JOIN proposals p ON p.id = a.proposal_id ORDER BY a.id DESC LIMIT 8');

$months = [];
for ($i = 5; $i >= 0; $i--) $months[date('Y-m', strtotime("-$i months"))] = ['created' => 0, 'accepted' => 0];
foreach (db_all("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) c, SUM(status='accepted') a FROM proposals WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY m") as $r) {
    if (isset($months[$r['m']])) $months[$r['m']] = ['created' => (int) $r['c'], 'accepted' => (int) $r['a']];
}

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/layouts/header.php';
$cards = [
    ['Total Proposals', $total, 'bi-files', 'ic-1'],
    ['Drafts', $counts['draft'], 'bi-pencil', 'ic-2'],
    ['Sent', $counts['sent'], 'bi-send', 'ic-3'],
    ['Viewed', $counts['viewed'], 'bi-eye', 'ic-4'],
    ['Accepted', $counts['accepted'], 'bi-check2-circle', 'ic-5'],
    ['Rejected', $counts['rejected'], 'bi-x-circle', 'ic-6'],
    ['Expired', $counts['expired'], 'bi-hourglass-bottom', 'ic-7'],
];
?>
<div class="page-head">
  <div><h1>Welcome back, <?= e(explode(' ', current_user()['name'])[0]) ?> 👋</h1><p>Here’s what’s happening with your proposals.</p></div>
  <div class="actions"><a href="<?= url('proposal-create.php') ?>" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Create Proposal</a></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-4 col-md-6">
    <div class="card-x stat" style="background:linear-gradient(135deg,#015957,#0e7c78);border:0;color:#fff">
      <div class="ic" style="background:rgba(255,255,255,.15);color:#fff"><i class="bi bi-cash-stack"></i></div>
      <div><div class="l" style="color:rgba(255,255,255,.75)">Total Proposal Value</div><div class="v text-white"><?= e(money($value, $sym)) ?></div><div class="small" style="color:rgba(255,255,255,.75)"><?= e(money($won, $sym)) ?> won · <?= $winRate ?>% win rate</div></div>
    </div>
  </div>
  <?php foreach (array_slice($cards, 0, 2) as $c): ?>
  <div class="col-xl-4 col-md-6"><div class="card-x stat"><div class="ic <?= $c[3] ?>"><i class="bi <?= $c[2] ?>"></i></div><div><div class="v"><?= (int) $c[1] ?></div><div class="l"><?= $c[0] ?></div></div></div></div>
  <?php endforeach; ?>
</div>
<div class="row g-3 mb-4">
  <?php foreach (array_slice($cards, 2) as $c): ?>
  <div class="col"><div class="card-x stat"><div class="ic <?= $c[3] ?>"><i class="bi <?= $c[2] ?>"></i></div><div><div class="v"><?= (int) $c[1] ?></div><div class="l"><?= $c[0] ?></div></div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="card-x h-100"><div class="card-head"><h6>Proposals — last 6 months</h6></div><div class="card-body"><canvas id="chMonths" height="110"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card-x h-100"><div class="card-head"><h6>Status breakdown</h6></div><div class="card-body"><canvas id="chStatus" height="220"></canvas></div></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-9">
    <div class="card-x">
      <div class="card-head"><h6>Recent Proposals</h6><a href="<?= url('proposals.php') ?>" class="ms-auto small fw-semibold">View all <i class="bi bi-arrow-right"></i></a></div>
      <div class="table-responsive"><table class="table table-x">
        <thead><tr><th>Proposal</th><th>Client</th><th>Service</th><th>Amount</th><th>Status</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
        <tbody><?= proposal_rows($recent) ?></tbody>
      </table></div>
    </div>
  </div>
  <div class="col-xl-3">
    <div class="card-x h-100"><div class="card-head"><h6>Recent Activity</h6></div><div class="card-body">
      <?php if (!$activity): ?><div class="text-muted small">No activity yet.</div><?php endif; ?>
      <ul class="timeline-x">
        <?php foreach ($activity as $a): ?>
          <li><div class="small fw-semibold text-dark"><?= e(ucfirst(str_replace('_', ' ', $a['action']))) ?></div>
            <div class="small text-muted"><a href="<?= url('proposal.php?id=' . (int) $a['proposal_id']) ?>"><?= e($a['proposal_number']) ?></a> · <?= e(time_ago($a['created_at'])) ?></div></li>
        <?php endforeach; ?>
      </ul>
    </div></div>
  </div>
</div>
<?php
$extraScripts = '<script src="' . asset('vendor/chartjs/chart.umd.min.js') . '"></script><script>
(function(){
  var m = ' . json_encode($months) . ';
  var labels = Object.keys(m).map(function(k){ var d = new Date(k + "-01"); return d.toLocaleString("en", {month:"short"}); });
  new Chart(document.getElementById("chMonths"), {type:"bar", data:{labels:labels, datasets:[
    {label:"Created", data:Object.values(m).map(function(x){return x.created}), backgroundColor:"#99d5cf", borderRadius:8},
    {label:"Accepted", data:Object.values(m).map(function(x){return x.accepted}), backgroundColor:"#015957", borderRadius:8}]},
    options:{plugins:{legend:{position:"bottom"}}, scales:{y:{beginAtZero:true, ticks:{precision:0}, grid:{color:"#eef2f7"}}, x:{grid:{display:false}}}}});
  var s = ' . json_encode($counts) . ';
  new Chart(document.getElementById("chStatus"), {type:"doughnut", data:{labels:["Draft","Sent","Viewed","Accepted","Rejected","Expired"],
    datasets:[{data:[s.draft,s.sent,s.viewed,s.accepted,s.rejected,s.expired], backgroundColor:["#cbd5e1","#3b82f6","#06b6d4","#16a34a","#ef4444","#f59e0b"], borderWidth:0}]},
    options:{cutout:"68%", plugins:{legend:{position:"bottom", labels:{boxWidth:10, usePointStyle:true}}}}});
})();
</script>';
include __DIR__ . '/layouts/footer.php';
