<?php
require_once __DIR__ . '/../config/config.php';

$token = clean_input($_GET['token'] ?? '');
$trip = $token ? db_select_one(
    $conn,
    'SELECT t.*, c.name AS city_name, u.name AS owner_name FROM trips t LEFT JOIN cities c ON c.id = t.destination_city_id JOIN users u ON u.id = t.user_id WHERE t.share_token = ? AND t.visibility = "public"',
    [$token]
) : null;

if (!$trip) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

$budget = db_select_one($conn, 'SELECT * FROM trip_budget WHERE trip_id = ?', [(int) $trip['id']]);
$days = db_select($conn, 'SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number', [(int) $trip['id']]);
$itemsByDay = [];
foreach ($days as $day) {
    $itemsByDay[$day['id']] = db_select(
        $conn,
        'SELECT ti.*, s.title AS service_title, s.slug AS service_slug, s.price, s.price_unit, cat.name AS category_name
         FROM trip_items ti LEFT JOIN services s ON s.id = ti.service_id LEFT JOIN categories cat ON cat.id = s.category_id
         WHERE ti.trip_day_id = ? ORDER BY ti.sort_order',
        [$day['id']]
    );
}

$pageTitle = $trip['trip_name'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:820px;">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-map"></i> Shared trip by <?php echo e($trip['owner_name']); ?></span>
      <h1 class="section-heading"><?php echo e($trip['trip_name']); ?></h1>
      <p class="section-sub"><i class="bi bi-geo-alt"></i> <?php echo e($trip['city_name'] ?? '—'); ?><?php echo $trip['date_from'] ? ' · ' . e(format_date($trip['date_from'])) . ($trip['date_to'] ? ' – ' . e(format_date($trip['date_to'])) : '') : ''; ?></p>
    </div>

    <?php if ($budget): ?>
    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:14px;">Estimated budget</h3>
      <div style="display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;gap:24px;font-size:13px;color:var(--ink-mute);">
          <span>Hotel: <strong style="color:var(--ink);"><?php echo format_price($budget['hotel_total']); ?></strong></span>
          <span>Transport: <strong style="color:var(--ink);"><?php echo format_price($budget['transport_total']); ?></strong></span>
          <span>Food: <strong style="color:var(--ink);"><?php echo format_price($budget['food_total']); ?></strong></span>
          <span>Activities: <strong style="color:var(--ink);"><?php echo format_price($budget['activities_total']); ?></strong></span>
        </div>
        <div class="price-tag" style="font-size:20px;">~<?php echo format_price($budget['estimated_total']); ?></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:16px;">Itinerary</h3>
      <?php foreach ($days as $day): ?>
        <div style="margin-bottom:22px;">
          <h4 style="font-size:14px;font-weight:800;margin-bottom:10px;">Day <?php echo (int) $day['day_number']; ?><?php echo $day['day_date'] ? ' — ' . e(format_date($day['day_date'])) : ''; ?></h4>
          <?php foreach ($itemsByDay[$day['id']] as $item): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--bg);border-radius:10px;margin-bottom:8px;">
              <div style="flex:1;">
                <?php if ($item['service_id']): ?>
                  <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($item['service_slug']); ?>" style="font-weight:700;font-size:13.5px;color:var(--ink);"><?php echo e($item['service_title']); ?></a>
                  <div style="font-size:12px;color:var(--ink-mute);"><?php echo e($item['category_name'] ?? ''); ?></div>
                <?php else: ?>
                  <div style="font-weight:700;font-size:13.5px;"><?php echo e($item['custom_title']); ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (!$itemsByDay[$day['id']]): ?><p style="font-size:13px;color:var(--ink-mute);">Nothing planned yet.</p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center;">
      <a href="<?php echo url('/pages/trip-planner.php'); ?>" class="btn-w btn-primary">Plan your own trip <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
