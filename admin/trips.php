<?php
require_once __DIR__ . '/../config/config.php';
require_login('admin');
$admin = current_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $tripId = (int) ($_POST['trip_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $trip = $tripId ? db_select_one($conn, 'SELECT * FROM trips WHERE id = ?', [$tripId]) : null;

    if (!$trip) {
        flash_set('danger', 'Trip not found.');
        redirect('/admin/trips.php');
    }

    if ($action === 'make_private') {
        db_execute($conn, 'UPDATE trips SET visibility = "private" WHERE id = ?', [$tripId]);
        $msg = 'Trip hidden from public view.';
    } elseif ($action === 'delete') {
        db_execute($conn, 'DELETE FROM trips WHERE id = ?', [$tripId]);
        $msg = 'Trip deleted.';
    } else {
        flash_set('danger', 'Invalid action.');
        redirect('/admin/trips.php');
    }

    log_audit($conn, (int) $admin['id'], 'trip', $tripId, $action);
    flash_set('success', $msg);
    redirect('/admin/trips.php' . (!empty($_POST['back_qs']) ? '?' . $_POST['back_qs'] : ''));
}

$viewId = (int) ($_GET['view'] ?? 0);

if ($viewId) {
    $trip = db_select_one(
        $conn,
        'SELECT t.*, u.name AS customer_name, u.email AS customer_email, c.name AS city_name
         FROM trips t JOIN users u ON u.id = t.user_id LEFT JOIN cities c ON c.id = t.destination_city_id
         WHERE t.id = ?',
        [$viewId]
    );
    if (!$trip) {
        flash_set('danger', 'Trip not found.');
        redirect('/admin/trips.php');
    }
    $days = db_select($conn, 'SELECT * FROM trip_days WHERE trip_id = ? ORDER BY day_number', [$viewId]);
    $itemsByDay = [];
    foreach ($days as $d) {
        $itemsByDay[$d['id']] = db_select(
            $conn,
            'SELECT ti.*, s.title AS service_title FROM trip_items ti LEFT JOIN services s ON s.id = ti.service_id WHERE ti.trip_day_id = ? ORDER BY ti.sort_order, ti.start_time',
            [$d['id']]
        );
    }
    $budget = db_select_one($conn, 'SELECT * FROM trip_budget WHERE trip_id = ?', [$viewId]);

    $adminPageTitle = 'Trip Planner';
    $adminActive = 'trips';
    require __DIR__ . '/_layout_top.php';
    ?>
    <a href="<?php echo url('/admin/trips.php'); ?>" style="color:var(--ink-mute);font-size:13.5px;"><i class="bi bi-arrow-left"></i> Back to all trips</a>

    <div class="panel" style="margin-top:16px;">
      <div class="panel-head">
        <h3><?php echo e($trip['trip_name']); ?></h3>
        <div style="display:flex;gap:8px;">
          <?php echo status_badge($trip['status']); ?>
          <span class="status-chip <?php echo $trip['visibility'] === 'public' ? 'approved' : 'draft'; ?>"><i class="bi bi-<?php echo $trip['visibility'] === 'public' ? 'globe' : 'lock'; ?>"></i> <?php echo ucfirst($trip['visibility']); ?></span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;font-size:13.5px;">
        <div><div style="color:var(--ink-mute);">Traveler</div><strong><?php echo e($trip['customer_name']); ?></strong><div style="color:var(--ink-mute);"><?php echo e($trip['customer_email']); ?></div></div>
        <div><div style="color:var(--ink-mute);">Destination</div><strong><?php echo e($trip['city_name'] ?: '—'); ?></strong></div>
        <div><div style="color:var(--ink-mute);">Dates</div><strong><?php echo $trip['date_from'] ? e(format_date($trip['date_from'])) . ' – ' . e(format_date($trip['date_to'])) : '—'; ?></strong></div>
        <div><div style="color:var(--ink-mute);">Travelers</div><strong><?php echo (int) $trip['adults']; ?> adults<?php echo $trip['children'] ? ', ' . (int) $trip['children'] . ' children' : ''; ?></strong></div>
      </div>
      <?php if ($trip['notes']): ?><p style="margin-top:16px;color:var(--ink-soft);font-size:14px;"><?php echo nl2br(e($trip['notes'])); ?></p><?php endif; ?>

      <div style="display:flex;gap:8px;margin-top:18px;">
        <?php if ($trip['visibility'] === 'public'): ?>
          <form method="post" onsubmit="return confirm('Hide this trip from public view?');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
            <input type="hidden" name="action" value="make_private">
            <button type="submit" class="btn-w btn-outline btn-sm"><i class="bi bi-eye-slash"></i> Make private</button>
          </form>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('Delete this trip permanently? This cannot be undone.');">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="trip_id" value="<?php echo (int) $trip['id']; ?>">
          <input type="hidden" name="action" value="delete">
          <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i> Delete trip</button>
        </form>
      </div>
    </div>

    <?php if ($budget): ?>
      <div class="panel">
        <div class="panel-head"><h3>Budget breakdown</h3></div>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:14px;text-align:center;">
          <div><div style="color:var(--ink-mute);font-size:12px;">Hotels</div><strong><?php echo format_price($budget['hotel_total']); ?></strong></div>
          <div><div style="color:var(--ink-mute);font-size:12px;">Transport</div><strong><?php echo format_price($budget['transport_total']); ?></strong></div>
          <div><div style="color:var(--ink-mute);font-size:12px;">Food</div><strong><?php echo format_price($budget['food_total']); ?></strong></div>
          <div><div style="color:var(--ink-mute);font-size:12px;">Activities</div><strong><?php echo format_price($budget['activities_total']); ?></strong></div>
          <div><div style="color:var(--ink-mute);font-size:12px;">Fees & tax</div><strong><?php echo format_price((float) $budget['fees_total'] + (float) $budget['tax_total']); ?></strong></div>
          <div><div style="color:var(--purple-600);font-size:12px;">Estimated total</div><strong style="color:var(--purple-600);"><?php echo format_price($budget['estimated_total']); ?></strong></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel-head"><h3>Itinerary</h3></div>
      <?php if ($days): ?>
        <?php foreach ($days as $d): ?>
          <div style="border-bottom:1px solid var(--border);padding:14px 0;">
            <strong>Day <?php echo (int) $d['day_number']; ?></strong>
            <?php if ($d['day_date']): ?><span style="color:var(--ink-mute);font-size:13px;"> · <?php echo e(format_date($d['day_date'])); ?></span><?php endif; ?>
            <?php if (!empty($itemsByDay[$d['id']])): ?>
              <ul style="margin:10px 0 0;padding-left:20px;font-size:14px;color:var(--ink-soft);">
                <?php foreach ($itemsByDay[$d['id']] as $it): ?>
                  <li>
                    <?php if ($it['start_time']): ?><code style="font-size:12px;"><?php echo e(substr($it['start_time'], 0, 5)); ?></code> <?php endif; ?>
                    <?php echo e($it['service_title'] ?: $it['custom_title']); ?>
                    <?php if ($it['item_type']): ?><span style="color:var(--ink-mute);"> (<?php echo e($it['item_type']); ?>)</span><?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p style="margin:6px 0 0;color:var(--ink-mute);font-size:13.5px;">Nothing added for this day yet.</p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-state" style="padding:24px;"><div class="icon-wrap"><i class="bi bi-map"></i></div><h4>No itinerary days yet</h4></div>
      <?php endif; ?>
    </div>

    <?php require __DIR__ . '/_layout_bottom.php'; ?>
    <?php
    exit;
}

$statusFilter = clean_input($_GET['status'] ?? '');
$visibilityFilter = clean_input($_GET['visibility'] ?? '');
$q = clean_input($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];
if ($statusFilter !== '') {
    $where[] = 't.status = ?';
    $params[] = $statusFilter;
}
if ($visibilityFilter !== '') {
    $where[] = 't.visibility = ?';
    $params[] = $visibilityFilter;
}
if ($q !== '') {
    $where[] = '(t.trip_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$whereSql = implode(' AND ', $where);

$pg = paginate($conn, "SELECT COUNT(*) FROM trips t JOIN users u ON u.id = t.user_id WHERE $whereSql", $params, $page, 20);

$trips = db_select(
    $conn,
    "SELECT t.*, u.name AS customer_name, c.name AS city_name,
        (SELECT COUNT(*) FROM trip_items ti JOIN trip_days td ON td.id = ti.trip_day_id WHERE td.trip_id = t.id) AS item_count
     FROM trips t JOIN users u ON u.id = t.user_id LEFT JOIN cities c ON c.id = t.destination_city_id
     WHERE $whereSql ORDER BY t.created_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);

$stats = db_select_one($conn, 'SELECT COUNT(*) AS total, SUM(visibility = "public") AS public_count, SUM(status = "planned") AS planned_count FROM trips');

$adminPageTitle = 'Trip Planner';
$adminActive = 'trips';
require __DIR__ . '/_layout_top.php';
$qs = http_build_query(['status' => $statusFilter, 'visibility' => $visibilityFilter, 'q' => $q]);
?>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-map"></i></div><div class="value"><?php echo (int) $stats['total']; ?></div><div class="label">Trips created</div></div>
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-globe"></i></div><div class="value"><?php echo (int) $stats['public_count']; ?></div><div class="label">Shared publicly</div></div>
  <div class="stat-card"><div class="icon-wrap"><i class="bi bi-check2-circle"></i></div><div class="value"><?php echo (int) $stats['planned_count']; ?></div><div class="label">Fully planned</div></div>
</div>

<div class="panel">
  <div class="panel-head">
    <h3>All trips</h3>
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;">
      <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search trip or traveler" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;min-width:220px;">
      <select name="status" onchange="this.form.submit()" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
        <option value="">All statuses</option>
        <?php foreach (['draft', 'planned', 'completed', 'cancelled'] as $s): ?>
          <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
        <?php endforeach; ?>
      </select>
      <select name="visibility" onchange="this.form.submit()" style="padding:9px 14px;border-radius:999px;border:1.5px solid var(--border);font-size:13.5px;">
        <option value="">Public + private</option>
        <option value="public" <?php echo $visibilityFilter === 'public' ? 'selected' : ''; ?>>Public only</option>
        <option value="private" <?php echo $visibilityFilter === 'private' ? 'selected' : ''; ?>>Private only</option>
      </select>
      <button class="btn-w btn-outline btn-sm" type="submit"><i class="bi bi-search"></i></button>
    </form>
  </div>

  <?php if ($trips): ?>
    <table class="table-w">
      <thead><tr><th>Trip</th><th>Traveler</th><th>Destination</th><th>Dates</th><th>Items</th><th>Status</th><th>Visibility</th><th style="text-align:right;">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($trips as $t): ?>
          <tr>
            <td><a href="?view=<?php echo (int) $t['id']; ?>" style="font-weight:600;color:var(--ink);"><?php echo e($t['trip_name']); ?></a></td>
            <td><?php echo e($t['customer_name']); ?></td>
            <td><?php echo e($t['city_name'] ?: '—'); ?></td>
            <td><?php echo $t['date_from'] ? e(format_date($t['date_from'], 'M j')) : '—'; ?></td>
            <td><?php echo (int) $t['item_count']; ?></td>
            <td><?php echo status_badge($t['status']); ?></td>
            <td><span class="status-chip <?php echo $t['visibility'] === 'public' ? 'approved' : 'draft'; ?>"><?php echo ucfirst($t['visibility']); ?></span></td>
            <td style="text-align:right;white-space:nowrap;">
              <a href="?view=<?php echo (int) $t['id']; ?>" class="btn-w btn-outline btn-sm">View</a>
              <form method="post" style="display:inline;" onsubmit="return confirm('Delete <?php echo e($t['trip_name']); ?>?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="trip_id" value="<?php echo (int) $t['id']; ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="back_qs" value="<?php echo e($qs); ?>">
                <button type="submit" class="btn-w btn-ghost btn-sm" style="color:var(--danger);"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($pg['total_pages'] > 1): ?>
      <div style="display:flex;gap:6px;justify-content:center;margin-top:18px;">
        <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
          <a href="?page=<?php echo $i; ?>&<?php echo e($qs); ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-map"></i></div><h4>No trips match this filter</h4></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_layout_bottom.php'; ?>
