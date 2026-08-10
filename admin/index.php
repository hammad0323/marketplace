<?php
require_once __DIR__ . '/../config/config.php';

$adminPageTitle = 'Dashboard';
$adminActive = 'dashboard';

$stats = [
    'users' => db_count($conn, 'SELECT COUNT(*) FROM users WHERE status = "active"'),
    'providers' => db_count($conn, 'SELECT COUNT(*) FROM providers'),
    'verified_providers' => db_count($conn, 'SELECT COUNT(*) FROM providers WHERE is_verified = 1'),
    'pending_providers' => db_count($conn, 'SELECT COUNT(*) FROM providers WHERE status = "pending"'),
    'cities' => db_count($conn, 'SELECT COUNT(*) FROM cities'),
    'categories' => db_count($conn, 'SELECT COUNT(*) FROM categories'),
    'services' => db_count($conn, 'SELECT COUNT(*) FROM services'),
    'bookings' => db_count($conn, 'SELECT COUNT(*) FROM bookings'),
];

$pendingProviders = db_select(
    $conn,
    'SELECT p.*, u.email, c.name AS city_name FROM providers p
     JOIN users u ON u.id = p.user_id LEFT JOIN cities c ON c.id = p.city_id
     WHERE p.status = "pending" ORDER BY p.created_at DESC LIMIT 8'
);

$recentUsers = db_select(
    $conn,
    'SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at DESC LIMIT 8'
);

require __DIR__ . '/_layout_top.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-people"></i></div>
    <div class="value"><?php echo (int) $stats['users']; ?></div>
    <div class="label">Active users</div>
  </div>
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-shop"></i></div>
    <div class="value"><?php echo (int) $stats['providers']; ?></div>
    <div class="label">Providers (<?php echo (int) $stats['pending_providers']; ?> pending)</div>
  </div>
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-patch-check"></i></div>
    <div class="value"><?php echo (int) $stats['verified_providers']; ?></div>
    <div class="label">Verified providers</div>
  </div>
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-calendar-check"></i></div>
    <div class="value"><?php echo (int) $stats['bookings']; ?></div>
    <div class="label">Total bookings</div>
  </div>
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-geo-alt"></i></div>
    <div class="value"><?php echo (int) $stats['cities']; ?></div>
    <div class="label">Cities</div>
  </div>
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-grid"></i></div>
    <div class="value"><?php echo (int) $stats['categories']; ?></div>
    <div class="label">Categories</div>
  </div>
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-list-ul"></i></div>
    <div class="value"><?php echo (int) $stats['services']; ?></div>
    <div class="label">Services listed</div>
  </div>
  <div class="stat-card">
    <div class="icon-wrap"><i class="bi bi-hourglass-split"></i></div>
    <div class="value"><?php echo (int) $stats['pending_providers']; ?></div>
    <div class="label">Awaiting approval</div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3>Providers awaiting approval</h3>
    <span class="soon-tag" style="background:var(--purple-50);color:var(--purple-600);padding:4px 10px;border-radius:999px;font-size:11px;">Approve/reject actions ship in Phase 2</span>
  </div>
  <?php if ($pendingProviders): ?>
    <table class="table-w">
      <thead><tr><th>Business</th><th>Email</th><th>City</th><th>Submitted</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($pendingProviders as $p): ?>
          <tr>
            <td><strong><?php echo e($p['business_name']); ?></strong></td>
            <td><?php echo e($p['email']); ?></td>
            <td><?php echo e($p['city_name'] ?? '—'); ?></td>
            <td><?php echo e(time_ago($p['created_at'])); ?></td>
            <td><span class="status-chip pending">Pending</span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;">
      <div class="icon-wrap"><i class="bi bi-check2-circle"></i></div>
      <h4>Nothing waiting on you</h4>
      <p>New provider applications will show up here.</p>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head"><h3>Recently joined</h3></div>
  <?php if ($recentUsers): ?>
    <table class="table-w">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
      <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td><strong><?php echo e($u['name']); ?></strong></td>
            <td><?php echo e($u['email']); ?></td>
            <td><span class="status-chip active"><?php echo e(ucfirst($u['role_slug'])); ?></span></td>
            <td><?php echo e(time_ago($u['created_at'])); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;">
      <div class="icon-wrap"><i class="bi bi-people"></i></div>
      <h4>No users yet</h4>
    </div>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head"><h3>Build roadmap</h3></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
    <div class="roadmap-card"><div class="icon-wrap"><i class="bi bi-shop"></i></div><div><strong>Phase 2</strong><div style="font-size:13px;color:var(--ink-mute);">Full admin CRUD: providers, customers, categories, cities</div></div></div>
    <div class="roadmap-card"><div class="icon-wrap"><i class="bi bi-map"></i></div><div><strong>Phase 3</strong><div style="font-size:13px;color:var(--ink-mute);">Services, dynamic category fields, maps</div></div></div>
    <div class="roadmap-card"><div class="icon-wrap"><i class="bi bi-search"></i></div><div><strong>Phase 4</strong><div style="font-size:13px;color:var(--ink-mute);">AJAX search, filters, geolocation</div></div></div>
    <div class="roadmap-card"><div class="icon-wrap"><i class="bi bi-calendar-check"></i></div><div><strong>Phase 5</strong><div style="font-size:13px;color:var(--ink-mute);">Availability, calendar, reservations</div></div></div>
    <div class="roadmap-card"><div class="icon-wrap"><i class="bi bi-speedometer2"></i></div><div><strong>Phase 6</strong><div style="font-size:13px;color:var(--ink-mute);">Customer &amp; provider dashboards</div></div></div>
    <div class="roadmap-card"><div class="icon-wrap"><i class="bi bi-chat-dots"></i></div><div><strong>Phase 7</strong><div style="font-size:13px;color:var(--ink-mute);">Messaging, notifications, email</div></div></div>
  </div>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
