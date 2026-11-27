<?php
/** /manage — dashboard */
$me = require_admin('dashboard');
$today = date('Y-m-d');
$stats = array(
    array('Mosques', (int)db_val('SELECT COUNT(*) FROM th_venue WHERE role_id = 1'), 'bi-building', 'venues?type=mosque'),
    array('Organizations', (int)db_val('SELECT COUNT(*) FROM th_venue WHERE role_id <> 1'), 'bi-diagram-3', 'venues?type=org'),
    array('Verified profiles', (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 1'), 'bi-patch-check', 'venues?type=mosque&status=1'),
    array('Pending profiles', (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 0'), 'bi-hourglass-split', 'approvals'),
    array('Programs', (int)db_val('SELECT COUNT(*) FROM th_details WHERE grand_category_id = 2'), 'bi-journal-bookmark', 'details?gc=2'),
    array('Services', (int)db_val('SELECT COUNT(*) FROM th_details WHERE grand_category_id = 3'), 'bi-hand-thumbs-up', 'details?gc=3'),
    array('Events', (int)db_val('SELECT COUNT(*) FROM th_details WHERE grand_category_id = 1'), 'bi-calendar-event', 'details?gc=1'),
    array('Blogs', (int)db_val('SELECT COUNT(*) FROM blogs') + count(array_filter(storage_list('blogs'), function ($k) { return strpos($k, 'new-') === 0; })), 'bi-newspaper', 'blogs'),
    array('Venue accounts', (int)db_val("SELECT COUNT(*) FROM th_venue WHERE username IS NOT NULL AND username <> ''"), 'bi-person-badge', 'users?tab=venues'),
    array('Admins', (int)db_val('SELECT COUNT(*) FROM th_admin_table'), 'bi-person-gear', 'users'),
    array('With prayer times today', (int)db_val('SELECT COUNT(DISTINCT venue_id) FROM prayer_timings WHERE georgian_date = ?', 's', array($today)), 'bi-clock', 'prayer'),
    array('With current Jumu\'ah', (int)db_val('SELECT COUNT(DISTINCT venue_id) FROM th_jummah_prayers WHERE from_date <= ? AND to_date >= ?', 'ss', array($today, $today)), 'bi-people', 'jummah'),
);
$recent = db_all('SELECT v.venue_id, v.venue_title, v.role_id, v.verify_status, v.update_date, c.city_title FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id ORDER BY v.update_date DESC, v.venue_id DESC LIMIT 8');
$activity = admin_can('logs') ? db_all('SELECT l.*, a.admin_name, v.venue_title FROM th_display_audit_logs l LEFT JOIN th_admin_table a ON a.admin_id = l.admin_id LEFT JOIN th_venue v ON v.venue_id = l.venue_id ORDER BY l.id DESC LIMIT 10') : array();
$pending = approvals_count();
$noCoords = (int)db_val("SELECT COUNT(*) FROM th_venue WHERE role_id = 1 AND verify_status = 1") - count(venues_with_coords(1));
$legacyPw = (int)db_val("SELECT COUNT(*) FROM th_venue WHERE password IS NOT NULL AND password <> '' AND password NOT LIKE '\$2y\$%' AND password NOT LIKE '\$argon%'");
bo_start('admin', 'Dashboard');
?>
<p class="text-muted">Assalamu alaikum, <?= e($me['name']) ?>.</p>
<?php if ($pending): ?><div class="alert alert-warning d-flex align-items-center gap-2"><i class="bi bi-inbox"></i><div><strong><?= (int)$pending ?></strong> item(s) are waiting for approval.</div><a class="btn btn-sm btn-warning ms-auto" href="<?= e(bo_url('admin', 'approvals')) ?>">Review</a></div><?php endif; ?>
<div class="stat-tiles mb-4">
  <?php foreach ($stats as $s): ?>
  <a class="stat-tile" href="<?= e(bo_url('admin', $s[3])) ?>"><i class="bi <?= e($s[2]) ?>" aria-hidden="true"></i><span class="v"><?= number_format($s[1]) ?></span><span class="l"><?= e($s[0]) ?></span></a>
  <?php endforeach; ?>
</div>
<div class="row g-4">
  <div class="col-xl-6">
    <div class="card bo-card h-100">
      <div class="card-header d-flex"><h2 class="h6 m-0">Recently updated profiles</h2><a class="ms-auto small" href="<?= e(bo_url('admin', 'venues?type=mosque&sort=updated')) ?>">All</a></div>
      <ul class="list-group list-group-flush">
        <?php foreach ($recent as $r): ?>
        <li class="list-group-item d-flex align-items-center gap-2">
          <i class="bi <?= e(role_icon($r['role_id'])) ?> text-muted"></i>
          <a href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$r['venue_id'])) ?>"><?= e(trim($r['venue_title'])) ?></a>
          <span class="small text-muted"><?= e($r['city_title']) ?></span>
          <span class="ms-auto small text-muted"><?= e(fmt_date($r['update_date'])) ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card bo-card mb-4">
      <div class="card-header"><h2 class="h6 m-0">Data health</h2></div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item d-flex"><span><i class="bi bi-geo-alt me-2"></i>Mosques without a confirmed map location</span><a class="ms-auto fw-semibold" href="<?= e(bo_url('admin', 'locations')) ?>"><?= number_format(max(0, $noCoords)) ?></a></li>
        <li class="list-group-item d-flex"><span><i class="bi bi-shield-lock me-2"></i>Venue accounts with a legacy (unhashed) password</span><a class="ms-auto fw-semibold" href="<?= e(bo_url('admin', 'users?tab=security')) ?>"><?= number_format($legacyPw) ?></a></li>
        <li class="list-group-item d-flex"><span><i class="bi bi-hdd me-2"></i>Storage folder writable</span><span class="ms-auto fw-semibold <?= storage_writable() ? 'text-success' : 'text-danger' ?>"><?= storage_writable() ? 'Yes' : 'No — see README' ?></span></li>
        <li class="list-group-item d-flex"><span><i class="bi bi-image me-2"></i>Images folder writable</span><?php $md = media_dir() . '/images'; ?><span class="ms-auto fw-semibold <?= is_dir($md) && is_writable($md) ? 'text-success' : 'text-danger' ?>"><?= is_dir($md) && is_writable($md) ? 'Yes' : 'No (' . e($md) . ')' ?></span></li>
      </ul>
    </div>
    <?php if ($activity): ?>
    <div class="card bo-card">
      <div class="card-header d-flex"><h2 class="h6 m-0">Recent activity</h2><a class="ms-auto small" href="<?= e(bo_url('admin', 'logs')) ?>">Activity log</a></div>
      <ul class="list-group list-group-flush small">
        <?php foreach ($activity as $l): ?>
        <li class="list-group-item d-flex gap-2"><span class="text-muted text-nowrap"><?= e(date('j M H:i', strtotime($l['created_at']))) ?></span><span><strong><?= e($l['actor_type'] === 'admin' ? ($l['admin_name'] ?: 'Admin #' . $l['admin_id']) : excerpt($l['venue_title'] ?: 'Venue #' . $l['venue_id'], 30)) ?></strong> · <?= e($l['action']) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php bo_end();
