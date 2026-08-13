<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

$tripId = (int) ($_GET['id'] ?? 0);
$trip = db_select_one($conn, 'SELECT * FROM trips WHERE id = ? AND user_id = ?', [$tripId, (int) $user['id']]);
if (!$trip) {
    flash_set('danger', 'Trip not found.');
    redirect('/customer/trips.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = $_POST['form'] ?? '';

    if ($form === 'meta') {
        $budgetMode = in_array($_POST['budget_mode'] ?? '', ['economy', 'standard', 'luxury', 'custom'], true) ? $_POST['budget_mode'] : 'standard';
        $maxBudget = isset($_POST['max_budget']) && $_POST['max_budget'] !== '' ? (float) $_POST['max_budget'] : null;
        db_execute(
            $conn,
            'UPDATE trips SET trip_name=?, date_from=?, date_to=?, adults=?, children=?, budget_mode=?, max_budget=?, notes=? WHERE id=?',
            [
                clean_input($_POST['trip_name'] ?? $trip['trip_name']), clean_input($_POST['date_from'] ?? '') ?: null,
                clean_input($_POST['date_to'] ?? '') ?: null, max(1, (int) ($_POST['adults'] ?? 1)), max(0, (int) ($_POST['children'] ?? 0)),
                $budgetMode, $maxBudget,
                clean_input($_POST['notes'] ?? ''), $tripId,
            ]
        );
        generate_trip_days($conn, $tripId, clean_input($_POST['date_from'] ?? ''), clean_input($_POST['date_to'] ?? ''));
        flash_set('success', 'Trip details updated.');
    } elseif ($form === 'status') {
        $status = in_array($_POST['status'] ?? '', ['draft', 'planned', 'completed', 'cancelled'], true) ? $_POST['status'] : 'draft';
        db_execute($conn, 'UPDATE trips SET status = ? WHERE id = ?', [$status, $tripId]);
        flash_set('success', 'Status updated.');
    } elseif ($form === 'visibility') {
        $visibility = ($_POST['visibility'] ?? '') === 'public' ? 'public' : 'private';
        $shareToken = $trip['share_token'];
        if ($visibility === 'public' && !$shareToken) {
            $shareToken = bin2hex(random_bytes(16));
        }
        db_execute($conn, 'UPDATE trips SET visibility = ?, share_token = ? WHERE id = ?', [$visibility, $shareToken, $tripId]);
        flash_set('success', $visibility === 'public' ? 'Trip is now shareable via link.' : 'Trip is private again.');
    } elseif ($form === 'add_item') {
        $dayId = (int) ($_POST['trip_day_id'] ?? 0);
        $day = db_select_one($conn, 'SELECT id FROM trip_days WHERE id = ? AND trip_id = ?', [$dayId, $tripId]);
        if ($day) {
            $maxOrder = (int) (db_select_one($conn, 'SELECT COALESCE(MAX(sort_order),-1) AS m FROM trip_items WHERE trip_day_id = ?', [$dayId])['m']);
            db_execute(
                $conn,
                'INSERT INTO trip_items (trip_day_id, custom_title, item_type, start_time, sort_order, notes) VALUES (?,?,?,?,?,?)',
                [$dayId, clean_input($_POST['custom_title'] ?? ''), clean_input($_POST['item_type'] ?? 'activity'), clean_input($_POST['start_time'] ?? '') ?: null, $maxOrder + 1, clean_input($_POST['notes'] ?? '')]
            );
            flash_set('success', 'Item added.');
        }
    } elseif ($form === 'remove_item') {
        db_execute(
            $conn,
            'DELETE ti FROM trip_items ti JOIN trip_days td ON td.id = ti.trip_day_id WHERE ti.id = ? AND td.trip_id = ?',
            [(int) ($_POST['item_id'] ?? 0), $tripId]
        );
        flash_set('success', 'Item removed.');
    } elseif ($form === 'add_day') {
        $maxDay = (int) (db_select_one($conn, 'SELECT COALESCE(MAX(day_number),0) AS m FROM trip_days WHERE trip_id = ?', [$tripId])['m']);
        db_execute($conn, 'INSERT INTO trip_days (trip_id, day_number) VALUES (?, ?)', [$tripId, $maxDay + 1]);
        flash_set('success', 'Day added.');
    }

    recalculate_trip_budget($conn, $tripId);
    redirect('/customer/trip-builder.php?id=' . $tripId);
}

$trip = db_select_one($conn, 'SELECT t.*, c.name AS city_name FROM trips t LEFT JOIN cities c ON c.id = t.destination_city_id WHERE t.id = ?', [$tripId]);
$budget = recalculate_trip_budget($conn, $tripId);

$days = db_select($conn, 'SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number', [$tripId]);
$itemsByDay = [];
foreach ($days as $day) {
    $itemsByDay[$day['id']] = db_select(
        $conn,
        'SELECT ti.*, s.title AS service_title, s.slug AS service_slug, s.price, s.price_unit, cat.name AS category_name, cat.icon AS category_icon,
            (SELECT image_path FROM service_images si WHERE si.service_id = s.id ORDER BY is_cover DESC LIMIT 1) AS cover
         FROM trip_items ti LEFT JOIN services s ON s.id = ti.service_id LEFT JOIN categories cat ON cat.id = s.category_id
         WHERE ti.trip_day_id = ? ORDER BY ti.sort_order',
        [$day['id']]
    );
}

$pageTitle = $trip['trip_name'];
$customerActiveTab = 'trips';
$extraJs = '<script src="' . ASSETS_URL . '/js/trip-builder.js"></script>';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
      <div>
        <span class="eyebrow"><i class="bi bi-map"></i> Trip Planner</span>
        <h1 class="section-heading"><?php echo e($trip['trip_name']); ?></h1>
        <p class="section-sub"><i class="bi bi-geo-alt"></i> <?php echo e($trip['city_name'] ?? '—'); ?> · <?php echo (int) $trip['adults'] + (int) $trip['children']; ?> travelers<?php echo $trip['date_from'] ? ' · ' . e(format_date($trip['date_from'])) . ($trip['date_to'] ? ' – ' . e(format_date($trip['date_to'])) : '') : ''; ?></p>
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <form method="post" style="display:inline;">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="form" value="status">
          <select name="status" onchange="this.form.submit()" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13px;">
            <?php foreach (['draft', 'planned', 'completed', 'cancelled'] as $s): ?>
              <option value="<?php echo $s; ?>" <?php echo $trip['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
          </select>
        </form>
        <button type="button" class="btn-w btn-outline btn-sm" onclick="document.getElementById('share-panel').classList.toggle('open')"><i class="bi bi-share"></i> Share</button>
      </div>
    </div>

    <div id="share-panel" class="user-menu" style="position:static;width:100%;max-width:420px;margin-bottom:24px;display:none;padding:16px;">
      <form method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form" value="visibility">
        <label style="display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;margin-bottom:10px;">
          <input type="checkbox" name="visibility" value="public" <?php echo $trip['visibility'] === 'public' ? 'checked' : ''; ?> onchange="this.form.submit()" style="width:auto;"> Make this trip public
        </label>
        <?php if ($trip['visibility'] === 'public' && $trip['share_token']): ?>
          <div class="form-hint" style="word-break:break-all;background:var(--purple-50);padding:10px;border-radius:8px;">
            <?php echo e(APP_URL . '/pages/trip.php?token=' . $trip['share_token']); ?>
          </div>
        <?php endif; ?>
      </form>
    </div>
    <script>document.addEventListener('DOMContentLoaded',function(){var b=document.querySelector('[onclick*="share-panel"]');if(b)b.addEventListener('click',function(){document.getElementById('share-panel').style.display=document.getElementById('share-panel').style.display==='none'?'block':'none';});});</script>

    <!-- Budget dashboard -->
    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:16px;">Estimated budget</h3>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px;">
        <div style="text-align:center;"><div style="font-size:22px;font-weight:800;" data-count="<?php echo (int) $budget['hotel']; ?>">0</div><div style="font-size:12px;color:var(--ink-mute);">Hotel</div></div>
        <div style="text-align:center;"><div style="font-size:22px;font-weight:800;" data-count="<?php echo (int) $budget['transport']; ?>">0</div><div style="font-size:12px;color:var(--ink-mute);">Transport</div></div>
        <div style="text-align:center;"><div style="font-size:22px;font-weight:800;" data-count="<?php echo (int) $budget['food']; ?>">0</div><div style="font-size:12px;color:var(--ink-mute);">Food</div></div>
        <div style="text-align:center;"><div style="font-size:22px;font-weight:800;" data-count="<?php echo (int) $budget['activities']; ?>">0</div><div style="font-size:12px;color:var(--ink-mute);">Activities</div></div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;border-top:1px solid var(--border);padding-top:20px;">
        <div style="text-align:center;padding:14px;border-radius:12px;background:var(--bg);">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--ink-mute);">Minimum</div>
          <div style="font-size:20px;font-weight:800;margin-top:4px;" data-count="<?php echo (int) $budget['minimum']; ?>" data-suffix="">0</div>
        </div>
        <div style="text-align:center;padding:14px;border-radius:12px;background:var(--gradient-purple);color:#fff;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;opacity:0.85;">Recommended</div>
          <div style="font-size:24px;font-weight:800;margin-top:4px;" data-count="<?php echo (int) $budget['recommended']; ?>">0</div>
        </div>
        <div style="text-align:center;padding:14px;border-radius:12px;background:var(--bg);">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--ink-mute);">Premium</div>
          <div style="font-size:20px;font-weight:800;margin-top:4px;" data-count="<?php echo (int) $budget['premium']; ?>">0</div>
        </div>
      </div>
      <?php if ($trip['max_budget'] && $budget['recommended'] > $trip['max_budget']): ?>
        <div class="alert-w alert-danger" style="margin-top:16px;"><i class="bi bi-exclamation-triangle-fill"></i> This itinerary (<?php echo format_price($budget['recommended']); ?>) is over your budget of <?php echo format_price($trip['max_budget']); ?>.</div>
      <?php elseif ($trip['max_budget']): ?>
        <div class="alert-w alert-success" style="margin-top:16px;"><i class="bi bi-check-circle-fill"></i> Within your budget of <?php echo format_price($trip['max_budget']); ?>.</div>
      <?php endif; ?>
    </div>

    <!-- Itinerary -->
    <div class="panel">
      <div class="panel-head"><h3>Itinerary</h3>
        <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="form" value="add_day"><button type="submit" class="btn-w btn-outline btn-sm"><i class="bi bi-plus-lg"></i> Add day</button></form>
      </div>

      <?php foreach ($days as $day): ?>
        <div style="margin-bottom:28px;">
          <h4 style="font-size:14px;font-weight:800;margin-bottom:10px;">Day <?php echo (int) $day['day_number']; ?><?php echo $day['day_date'] ? ' — ' . e(format_date($day['day_date'])) : ''; ?></h4>
          <div class="trip-day-dropzone" data-day-id="<?php echo (int) $day['id']; ?>" style="min-height:40px;display:flex;flex-direction:column;gap:8px;">
            <?php foreach ($itemsByDay[$day['id']] as $item): ?>
              <div class="trip-item" draggable="true" data-item-id="<?php echo (int) $item['id']; ?>" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg);border-radius:10px;cursor:grab;">
                <i class="bi bi-grip-vertical" style="color:var(--ink-mute);"></i>
                <?php if ($item['cover']): ?><img src="<?php echo e($item['cover']); ?>" style="width:44px;height:44px;border-radius:8px;object-fit:cover;"><?php endif; ?>
                <div style="flex:1;">
                  <?php if ($item['service_id']): ?>
                    <a href="<?php echo url('/pages/service.php'); ?>?slug=<?php echo e($item['service_slug']); ?>" style="font-weight:700;font-size:13.5px;color:var(--ink);"><?php echo e($item['service_title']); ?></a>
                    <div style="font-size:12px;color:var(--ink-mute);"><?php echo e($item['category_name'] ?? ''); ?> · <?php echo format_price($item['price']); ?>/<?php echo e($item['price_unit']); ?></div>
                  <?php else: ?>
                    <div style="font-weight:700;font-size:13.5px;"><?php echo e($item['custom_title']); ?></div>
                    <div style="font-size:12px;color:var(--ink-mute);"><?php echo e(ucfirst($item['item_type'])); ?><?php echo $item['start_time'] ? ' · ' . e(substr($item['start_time'], 0, 5)) : ''; ?></div>
                  <?php endif; ?>
                </div>
                <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="form" value="remove_item"><input type="hidden" name="item_id" value="<?php echo (int) $item['id']; ?>"><button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-x-lg"></i></button></form>
              </div>
            <?php endforeach; ?>
          </div>
          <details style="margin-top:10px;">
            <summary style="cursor:pointer;font-size:13px;color:var(--purple-600);font-weight:600;">+ Add a custom item</summary>
            <form method="post" style="display:grid;grid-template-columns:1.5fr 1fr 1fr auto;gap:10px;margin-top:10px;align-items:end;">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="form" value="add_item">
              <input type="hidden" name="trip_day_id" value="<?php echo (int) $day['id']; ?>">
              <input type="text" name="custom_title" placeholder="e.g. Visit the old town" required style="padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);">
              <select name="item_type" style="padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);">
                <option value="activity">Activity</option><option value="hotel">Hotel</option><option value="restaurant">Restaurant</option><option value="transport">Transport</option><option value="note">Note</option>
              </select>
              <input type="time" name="start_time" style="padding:9px 12px;border-radius:8px;border:1.5px solid var(--border);">
              <button type="submit" class="btn-w btn-outline btn-sm">Add</button>
            </form>
          </details>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <h3 style="font-size:15px;margin-bottom:14px;">Trip details</h3>
      <form method="post" class="form-w" style="display:grid;grid-template-columns:1fr 1fr;gap:0 20px;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="form" value="meta">
        <div style="grid-column:span 2;"><label>Trip name</label><input type="text" name="trip_name" value="<?php echo e($trip['trip_name']); ?>"></div>
        <div><label>Start date</label><input type="date" name="date_from" value="<?php echo e($trip['date_from']); ?>"></div>
        <div><label>End date</label><input type="date" name="date_to" value="<?php echo e($trip['date_to']); ?>"></div>
        <div><label>Adults</label><input type="number" name="adults" min="1" value="<?php echo (int) $trip['adults']; ?>"></div>
        <div><label>Children</label><input type="number" name="children" min="0" value="<?php echo (int) $trip['children']; ?>"></div>
        <div>
          <label>Budget style</label>
          <select name="budget_mode">
            <?php foreach (['economy', 'standard', 'luxury', 'custom'] as $m): ?>
              <option value="<?php echo $m; ?>" <?php echo $trip['budget_mode'] === $m ? 'selected' : ''; ?>><?php echo ucfirst($m); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>Max budget (USD)</label><input type="number" name="max_budget" value="<?php echo e($trip['max_budget']); ?>"></div>
        <div style="grid-column:span 2;"><label>Notes</label><textarea name="notes" rows="3"><?php echo e($trip['notes']); ?></textarea></div>
        <div style="grid-column:span 2;"><button type="submit" class="btn-w btn-primary" style="margin-top:10px;">Save trip details</button></div>
      </form>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
