<?php
require_once __DIR__ . '/../config/config.php';
require_login('provider');
$user = current_user($conn);
$provider = db_select_one($conn, 'SELECT * FROM providers WHERE user_id = ?', [(int) $user['id']]);
if (!$provider) {
    redirect('/provider/index.php');
}

$services = db_select($conn, 'SELECT id, title FROM services WHERE provider_id = ? AND status != "rejected" ORDER BY title', [(int) $provider['id']]);
$serviceId = (int) ($_GET['service_id'] ?? ($services[0]['id'] ?? 0));
$service = $serviceId ? db_select_one($conn, 'SELECT * FROM services WHERE id = ? AND provider_id = ?', [$serviceId, (int) $provider['id']]) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $service) {
    verify_csrf();
    $from = clean_input($_POST['date_from'] ?? '');
    $to = clean_input($_POST['date_to'] ?? $from);
    $blockAction = ($_POST['range_action'] ?? 'block') === 'unblock' ? 'available' : 'blocked';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $to >= $from) {
        if ($blockAction === 'available') {
            db_execute($conn, 'DELETE FROM service_availability WHERE service_id = ? AND date BETWEEN ? AND ? AND status = "blocked"', [$serviceId, $from, $to]);
        } else {
            mark_service_dates($conn, $serviceId, $from, date('Y-m-d', strtotime($to . ' +1 day')), 'blocked');
        }
        flash_set('success', 'Calendar updated.');
    } else {
        flash_set('danger', 'Please choose a valid date range.');
    }
    redirect('/provider/availability.php?service_id=' . $serviceId . '&month=' . e($_GET['month'] ?? ''));
}

$monthParam = clean_input($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$monthStart = $monthParam . '-01';
$firstWeekday = (int) date('w', strtotime($monthStart));
$daysInMonth = (int) date('t', strtotime($monthStart));
$prevMonth = date('Y-m', strtotime($monthStart . ' -1 month'));
$nextMonth = date('Y-m', strtotime($monthStart . ' +1 month'));

$availability = [];
if ($service) {
    foreach (db_select($conn, 'SELECT date, status FROM service_availability WHERE service_id = ? AND date BETWEEN ? AND ?', [$serviceId, $monthStart, date('Y-m-d', strtotime($monthStart . ' +1 month -1 day'))]) as $row) {
        $availability[$row['date']] = $row['status'];
    }
}

$pageTitle = 'Availability Calendar';
$providerActiveTab = 'availability';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:840px;">
    <div class="section-head" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px;">
      <div>
        <span class="eyebrow"><i class="bi bi-calendar-week"></i> Provider</span>
        <h1 class="section-heading">Availability calendar</h1>
        <p class="section-sub">Click a date to block or unblock it. Purple dates already have a confirmed booking.</p>
      </div>
      <?php if ($services): ?>
        <form method="get" style="display:flex;gap:8px;">
          <select name="service_id" onchange="this.form.submit()" style="padding:10px 14px;border-radius:999px;border:1.5px solid var(--border);">
            <?php foreach ($services as $s): ?>
              <option value="<?php echo (int) $s['id']; ?>" <?php echo $s['id'] == $serviceId ? 'selected' : ''; ?>><?php echo e($s['title']); ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php endif; ?>
    </div>

    <?php require ROOT_PATH . '/includes/provider-tabs.php'; ?>

    <?php if (!$services): ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-list-ul"></i></div><h4>Add a service first</h4><p>Availability is managed per service.</p><a href="/provider/service-form.php" class="btn-w btn-primary">Add a service</a></div>
    <?php elseif (!$service): ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-exclamation-triangle"></i></div><h4>Service not found</h4></div>
    <?php else: ?>
      <div class="panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
          <a href="?service_id=<?php echo $serviceId; ?>&month=<?php echo $prevMonth; ?>" class="btn-w btn-outline btn-sm"><i class="bi bi-chevron-left"></i></a>
          <h3 style="font-size:16px;margin:0;"><?php echo date('F Y', strtotime($monthStart)); ?></h3>
          <a href="?service_id=<?php echo $serviceId; ?>&month=<?php echo $nextMonth; ?>" class="btn-w btn-outline btn-sm"><i class="bi bi-chevron-right"></i></a>
        </div>

        <div id="calendar-grid" data-service="<?php echo $serviceId; ?>" data-csrf="<?php echo e(csrf_token()); ?>" style="display:grid;grid-template-columns:repeat(7,1fr);gap:6px;">
          <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd): ?>
            <div style="text-align:center;font-size:11px;font-weight:700;color:var(--ink-mute);text-transform:uppercase;padding-bottom:6px;"><?php echo $wd; ?></div>
          <?php endforeach; ?>
          <?php for ($i = 0; $i < $firstWeekday; $i++): ?><div></div><?php endfor; ?>
          <?php for ($d = 1; $d <= $daysInMonth; $d++):
            $dateStr = $monthParam . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $status = $availability[$dateStr] ?? 'available';
            $isPast = $dateStr < date('Y-m-d');
            $bg = $status === 'reserved' ? 'var(--gradient-purple)' : ($status === 'blocked' ? '#FEE2E2' : 'var(--white)');
            $color = $status === 'reserved' ? '#fff' : ($status === 'blocked' ? '#991B1B' : 'var(--ink)');
          ?>
            <button type="button" class="cal-day" data-date="<?php echo $dateStr; ?>"
              <?php echo ($isPast || $status === 'reserved') ? 'disabled' : ''; ?>
              style="aspect-ratio:1;border-radius:10px;border:1px solid var(--border);background:<?php echo $bg; ?>;color:<?php echo $color; ?>;font-weight:600;font-size:13px;cursor:<?php echo ($isPast || $status === 'reserved') ? 'default' : 'pointer'; ?>;opacity:<?php echo $isPast ? '0.4' : '1'; ?>;transition:transform 0.15s ease;">
              <?php echo $d; ?>
            </button>
          <?php endfor; ?>
        </div>

        <div style="display:flex;gap:16px;margin-top:16px;font-size:12.5px;color:var(--ink-mute);">
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--white);border:1px solid var(--border);"></span> Available</span>
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#FEE2E2;"></span> Blocked</span>
          <span><span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:var(--purple);"></span> Booked</span>
        </div>
      </div>

      <div class="panel">
        <h3 style="font-size:15px;margin-bottom:14px;">Block or unblock a date range</h3>
        <form method="post" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;">
          <?php echo csrf_field(); ?>
          <div><label style="font-size:13px;font-weight:600;">From</label><input type="date" name="date_from" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
          <div><label style="font-size:13px;font-weight:600;">To</label><input type="date" name="date_to" required style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);"></div>
          <div>
            <label style="font-size:13px;font-weight:600;">Action</label>
            <select name="range_action" style="width:100%;padding:10px;border-radius:10px;border:1.5px solid var(--border);">
              <option value="block">Block dates</option>
              <option value="unblock">Unblock dates</option>
            </select>
          </div>
          <button type="submit" class="btn-w btn-primary btn-sm">Apply</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php $extraJs = '<script src="' . ASSETS_URL . '/js/calendar.js"></script>'; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
