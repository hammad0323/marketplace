<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);
if (!$provider) {
    redirect('/provider/index.php');
}

$totalViews = (int) db_select_one($conn, 'SELECT COALESCE(SUM(view_count),0) AS v FROM services WHERE provider_id = ?', [(int) $provider['id']])['v'];
$totalBookings = db_count($conn, 'SELECT COUNT(*) FROM bookings WHERE provider_id = ?', [(int) $provider['id']]);
$confirmedRevenue = (float) db_select_one($conn, 'SELECT COALESCE(SUM(total_amount),0) AS v FROM bookings WHERE provider_id = ? AND status IN ("confirmed","completed")', [(int) $provider['id']])['v'];
$conversion = $totalViews > 0 ? round(($totalBookings / $totalViews) * 100, 1) : 0;

$monthlyRows = db_select(
    $conn,
    'SELECT DATE_FORMAT(created_at, "%Y-%m") AS ym, COUNT(*) AS bookings, COALESCE(SUM(total_amount),0) AS revenue
     FROM bookings WHERE provider_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym ORDER BY ym',
    [(int) $provider['id']]
);
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $ym = date('Y-m', strtotime("-$i months"));
    $monthly[$ym] = ['label' => date('M', strtotime($ym . '-01')), 'bookings' => 0, 'revenue' => 0];
}
foreach ($monthlyRows as $row) {
    if (isset($monthly[$row['ym']])) {
        $monthly[$row['ym']]['bookings'] = (int) $row['bookings'];
        $monthly[$row['ym']]['revenue'] = (float) $row['revenue'];
    }
}

$topServices = db_select(
    $conn,
    'SELECT title, view_count, avg_rating, review_count FROM services WHERE provider_id = ? ORDER BY view_count DESC LIMIT 5',
    [(int) $provider['id']]
);

$pageTitle = 'Analytics';
$providerActiveTab = 'analytics';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-graph-up"></i> Provider</span>
      <h1 class="section-heading">Analytics</h1>
    </div>

    <?php require ROOT_PATH . '/includes/provider-tabs.php'; ?>

    <div class="stat-grid">
      <div class="stat-card"><div class="icon-wrap"><i class="bi bi-eye"></i></div><div class="value"><?php echo $totalViews; ?></div><div class="label">Total listing views</div></div>
      <div class="stat-card"><div class="icon-wrap"><i class="bi bi-person-badge"></i></div><div class="value"><?php echo (int) $provider['profile_views']; ?></div><div class="label">Profile visits</div></div>
      <div class="stat-card"><div class="icon-wrap"><i class="bi bi-calendar-check"></i></div><div class="value"><?php echo $totalBookings; ?></div><div class="label">Total bookings</div></div>
      <div class="stat-card"><div class="icon-wrap"><i class="bi bi-cash-stack"></i></div><div class="value"><?php echo format_price($confirmedRevenue); ?></div><div class="label">Confirmed revenue</div></div>
    </div>

    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:16px;">Bookings — last 6 months</h3>
      <canvas id="bookings-chart" height="90"></canvas>
    </div>

    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:14px;">Top services by views</h3>
      <?php if ($topServices): ?>
        <table class="table-w">
          <thead><tr><th>Service</th><th>Views</th><th>Rating</th><th>Reviews</th></tr></thead>
          <tbody>
            <?php foreach ($topServices as $s): ?>
              <tr><td><?php echo e($s['title']); ?></td><td><?php echo (int) $s['view_count']; ?></td><td><i class="bi bi-star-fill" style="color:#F59E0B;"></i> <?php echo number_format((float) $s['avg_rating'], 1); ?></td><td><?php echo (int) $s['review_count']; ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state" style="padding:24px;"><div class="icon-wrap"><i class="bi bi-graph-up"></i></div><h4>No data yet</h4></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
(function () {
  var data = <?php echo json_encode(array_values($monthly)); ?>;
  var canvas = document.getElementById('bookings-chart');
  var ctx = canvas.getContext('2d');
  var dpr = window.devicePixelRatio || 1;
  var w = canvas.clientWidth, h = 90;
  canvas.width = w * dpr; canvas.height = h * dpr;
  ctx.scale(dpr, dpr);

  var max = Math.max.apply(null, data.map(function (d) { return d.bookings; }).concat([1]));
  var barW = (w / data.length) * 0.5;
  var gap = (w / data.length);

  data.forEach(function (d, i) {
    var barH = (d.bookings / max) * (h - 24);
    var x = i * gap + (gap - barW) / 2;
    var y = h - barH - 18;
    var grad = ctx.createLinearGradient(0, y, 0, y + barH);
    grad.addColorStop(0, '#812288');
    grad.addColorStop(1, '#D185D6');
    ctx.fillStyle = grad;
    ctx.beginPath();
    if (ctx.roundRect) { ctx.roundRect(x, y, barW, barH, 4); } else { ctx.rect(x, y, barW, barH); }
    ctx.fill();
    ctx.fillStyle = '#7A7590';
    ctx.font = '11px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(d.label, x + barW / 2, h - 4);
  });
})();
</script>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
