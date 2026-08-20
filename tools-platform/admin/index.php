<?php
require __DIR__ . '/../includes/config.php';
require_admin();

$stats = [
    'total_tools' => (int) (tp_query_one("SELECT COUNT(*) c FROM tools")['c'] ?? 0),
    'published_tools' => (int) (tp_query_one("SELECT COUNT(*) c FROM tools WHERE status='published'")['c'] ?? 0),
    'draft_tools' => (int) (tp_query_one("SELECT COUNT(*) c FROM tools WHERE status='draft'")['c'] ?? 0),
    'total_categories' => (int) (tp_query_one("SELECT COUNT(*) c FROM categories")['c'] ?? 0),
    'total_views' => (int) (tp_query_one("SELECT COALESCE(SUM(views),0) c FROM tools")['c'] ?? 0),
    'today_views' => (int) (tp_query_one("SELECT COUNT(*) c FROM tool_views WHERE view_date = CURDATE()")['c'] ?? 0),
    'month_views' => (int) (tp_query_one("SELECT COUNT(*) c FROM tool_views WHERE view_date >= DATE_FORMAT(NOW(),'%Y-%m-01')")['c'] ?? 0),
];

$topTool = tp_query_one("SELECT name, views FROM tools ORDER BY views DESC LIMIT 1");
$trendingTool = tp_query_one("SELECT name, views FROM tools WHERE is_trending = 1 ORDER BY views DESC LIMIT 1");
$recentTools = tp_query("SELECT name, slug, status, views, created_at FROM tools ORDER BY created_at DESC LIMIT 8");

$dailyViews = tp_query(
    "SELECT view_date, COUNT(*) c FROM tool_views WHERE view_date >= (CURDATE() - INTERVAL 13 DAY) GROUP BY view_date ORDER BY view_date ASC"
);
$categoryViews = tp_query(
    "SELECT c.name, COALESCE(SUM(t.views),0) total FROM categories c LEFT JOIN tools t ON t.category_id = c.id GROUP BY c.id ORDER BY total DESC LIMIT 8"
);

$adminPageTitle = 'Dashboard';
require __DIR__ . '/includes/admin-header.php';
?>
<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['Total Tools', $stats['total_tools'], 'bi-tools', '#7C3AED'],
      ['Published', $stats['published_tools'], 'bi-check-circle', '#10B981'],
      ['Drafts', $stats['draft_tools'], 'bi-pencil-square', '#D97706'],
      ['Categories', $stats['total_categories'], 'bi-folder2', '#8B5CF6'],
      ['Total Views', $stats['total_views'], 'bi-eye', '#A78BFA'],
      ["Today's Views", $stats['today_views'], 'bi-calendar-day', '#5B21B6'],
      ['Monthly Views', $stats['month_views'], 'bi-calendar-month', '#6D28D9'],
  ];
  foreach ($cards as [$label, $value, $icon, $color]):
  ?>
  <div class="col-6 col-md-3 col-lg">
    <div class="stat-card">
      <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:1.4rem;"></i>
      <div class="stat-value mt-1"><?= number_format($value) ?></div>
      <div class="stat-label"><?= $label ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="admin-card">
      <h2 class="h6 fw-bold">Most Popular Tool</h2>
      <p class="mb-0"><?= $topTool ? e($topTool['name']) . ' — ' . number_format($topTool['views']) . ' views' : 'No data yet' ?></p>
    </div>
  </div>
  <div class="col-md-6">
    <div class="admin-card">
      <h2 class="h6 fw-bold">Trending Tool</h2>
      <p class="mb-0"><?= $trendingTool ? e($trendingTool['name']) . ' — ' . number_format($trendingTool['views']) . ' views' : 'No tool marked trending yet' ?></p>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="admin-card">
      <h2 class="h6 fw-bold mb-3">Daily Traffic (14 days)</h2>
      <canvas id="dailyViewsChart" height="110"></canvas>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="admin-card">
      <h2 class="h6 fw-bold mb-3">Views by Category</h2>
      <canvas id="categoryViewsChart" height="110"></canvas>
    </div>
  </div>
</div>

<div class="admin-card">
  <h2 class="h6 fw-bold mb-3">Recently Added Tools</h2>
  <table class="table table-sm">
    <thead><tr><th>Name</th><th>Status</th><th>Views</th><th>Added</th></tr></thead>
    <tbody>
      <?php foreach ($recentTools as $t): ?>
      <tr>
        <td><a href="<?= tp_url('admin/tool-form.php?slug=' . $t['slug']) ?>"><?= e($t['name']) ?></a></td>
        <td><span class="badge bg-<?= $t['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($t['status']) ?></span></td>
        <td><?= number_format($t['views']) ?></td>
        <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
new Chart(document.getElementById('dailyViewsChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($dailyViews, 'view_date')) ?>,
    datasets: [{ label: 'Views', data: <?= json_encode(array_map('intval', array_column($dailyViews, 'c'))) ?>, borderColor: '#7C3AED', backgroundColor: 'rgba(124,58,237,.1)', fill: true, tension: .35 }]
  },
  options: { plugins: { legend: { display: false } } }
});
new Chart(document.getElementById('categoryViewsChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($categoryViews, 'name')) ?>,
    datasets: [{ data: <?= json_encode(array_map('intval', array_column($categoryViews, 'total'))) ?>, backgroundColor: ['#7C3AED','#A78BFA','#8B5CF6','#5B21B6','#6D28D9','#C4B5FD','#4C1D95','#D8B4FE'] }]
  }
});
</script>
<?php require __DIR__ . '/includes/admin-footer.php'; ?>
