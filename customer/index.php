<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');

$user = current_user($conn);
$tripCount = db_count($conn, 'SELECT COUNT(*) FROM trips WHERE user_id = ?', [(int) $user['id']]);
$bookingCount = db_count($conn, 'SELECT COUNT(*) FROM bookings WHERE customer_id = ?', [(int) $user['id']]);
$favoriteCount = db_count($conn, 'SELECT COUNT(*) FROM favorites WHERE user_id = ?', [(int) $user['id']]);

$pageTitle = 'My Dashboard';
$customerActiveTab = 'dashboard';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-person"></i> Customer</span>
      <h1 class="section-heading">Welcome back, <?php echo e(explode(' ', $user['name'])[0]); ?></h1>
      <p class="section-sub">This is your travel command center.</p>
    </div>

    <?php require ROOT_PATH . '/includes/customer-tabs.php'; ?>

    <div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
      <a href="/customer/trips.php" class="stat-card" style="display:block;"><div class="icon-wrap"><i class="bi bi-map"></i></div><div class="value"><?php echo (int) $tripCount; ?></div><div class="label">Saved trips</div></a>
      <a href="/customer/bookings.php" class="stat-card" style="display:block;"><div class="icon-wrap"><i class="bi bi-calendar-check"></i></div><div class="value"><?php echo (int) $bookingCount; ?></div><div class="label">Bookings</div></a>
      <div class="stat-card"><div class="icon-wrap"><i class="bi bi-heart"></i></div><div class="value"><?php echo (int) $favoriteCount; ?></div><div class="label">Favorites</div></div>
    </div>

    <div class="cta-band" style="margin-top:24px;padding:32px;">
      <div>
        <h3 style="font-size:18px;">Haven't planned your next trip yet?</h3>
        <p style="font-size:13.5px;">Build a day-by-day itinerary with a live budget estimate in a couple of minutes.</p>
      </div>
      <a href="/pages/trip-planner.php" class="btn-w btn-white">Plan a trip <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
