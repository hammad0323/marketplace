<?php
/** /portal — dashboard for the signed-in mosque / organization */
$me = require_venue();
$id = (int)$me['id'];
$v = get_venue_any($id);
$isMosque = (int)$v['role_id'] === 1;
$tz = venue_timezone($v);
$today = today_in($tz);
// Profile completion
$checks = array(
    'Logo' => !empty($v['profile_image']), 'Cover image' => !empty($v['back_image']), 'About text' => trim(clean_text($v['about_details'])) !== '',
    'Short description' => trim((string)$v['meta_description']) !== '', 'Street address' => trim((string)$v['street_address']) !== '', 'Map location' => (bool)venue_coords($v),
    'Phone' => trim((string)$v['phone_number']) !== '', 'Email' => trim((string)$v['email_address']) !== '', 'Website or social link' => (bool)(safe_url($v['website_link']) || venue_social($v)),
);
$done = count(array_filter($checks));
$pct = (int)round($done / count($checks) * 100);
$counts = array(1 => 0, 2 => 0, 3 => 0);
$pendingDetails = 0;
foreach (db_all('SELECT grand_category_id gc, status_verify sv, COUNT(*) n FROM th_details WHERE venue_id = ? GROUP BY gc, sv', 'i', array($id)) as $r) {
    $counts[(int)$r['gc']] += (int)$r['n'];
    if ((int)$r['sv'] === 0) {
        $pendingDetails += (int)$r['n'];
    }
}
$upcoming = db_all('SELECT detail_id, details_title, start_date, end_date, status_verify FROM th_details WHERE venue_id = ? AND grand_category_id = 1 AND (end_date >= ? OR start_date >= ?) ORDER BY start_date LIMIT 5', 'iss', array($id, $today, $today));
$daysAhead = (int)db_val('SELECT COUNT(DISTINCT georgian_date) FROM prayer_timings WHERE venue_id = ? AND georgian_date >= ?', 'is', array($id, $today));
$lastDate = db_val('SELECT MAX(georgian_date) FROM prayer_timings WHERE venue_id = ?', 'i', array($id));
$jCurrent = (int)db_val('SELECT COUNT(*) FROM th_jummah_prayers WHERE venue_id = ? AND from_date <= ? AND to_date >= ?', 'iss', array($id, $today, $today));
$jUntil = db_val('SELECT MAX(to_date) FROM th_jummah_prayers WHERE venue_id = ?', 'i', array($id));
$activity = db_all("SELECT action, created_at, actor_type FROM th_display_audit_logs WHERE venue_id = ? OR (entity_type IN ('venue') AND entity_id = ?) ORDER BY id DESC LIMIT 8", 'ii', array($id, $id));
$pendingProfile = pending_profile($id);
bo_start('venue', 'Dashboard');
?>
<div class="portal-hero mb-4">
  <span class="mini-logo lg"><?php img_or_initials(venue_logo($v), '', $v['venue_title']); ?></span>
  <div>
    <h2 class="h4 mb-1"><?= e(trim($v['venue_title'])) ?></h2>
    <div class="text-muted small"><?= e(venue_place_line($v)) ?> · <?= (int)$v['verify_status'] === 1 ? '<span class="text-success"><i class="bi bi-patch-check-fill"></i> Verified &amp; public</span>' : '<span class="text-warning-emphasis"><i class="bi bi-hourglass-split"></i> Waiting for verification</span>' ?></div>
  </div>
</div>
<?php if ($pendingProfile): ?><div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>Your profile changes from <?= e(fmt_date($pendingProfile['submitted_at'], 'j M Y')) ?> are waiting for review.</div><?php endif; ?>
<div class="row g-4">
  <div class="col-lg-4">
    <div class="card bo-card h-100">
      <div class="card-header"><h2 class="h6 m-0">Profile completion</h2></div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3"><div class="ring" style="--p:<?= $pct ?>"><span><?= $pct ?>%</span></div><div class="small text-muted"><?= $done ?> of <?= count($checks) ?> done. Complete profiles get more visits.</div></div>
        <ul class="list-unstyled small mb-3">
          <?php foreach ($checks as $label => $ok): ?><li class="mb-1"><i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' ?> me-2"></i><?= e($label) ?></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-sm btn-primary" href="<?= e(bo_url('venue', 'profile')) ?>">Edit profile</a>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="stat-tiles mb-4">
      <a class="stat-tile" href="<?= e(bo_url('venue', 'details?gc=2')) ?>"><i class="bi bi-journal-bookmark"></i><span class="v"><?= $counts[2] ?></span><span class="l">Programs</span></a>
      <a class="stat-tile" href="<?= e(bo_url('venue', 'details?gc=3')) ?>"><i class="bi bi-hand-thumbs-up"></i><span class="v"><?= $counts[3] ?></span><span class="l">Services</span></a>
      <a class="stat-tile" href="<?= e(bo_url('venue', 'details?gc=1')) ?>"><i class="bi bi-calendar-event"></i><span class="v"><?= $counts[1] ?></span><span class="l">Events</span></a>
      <?php if ($pendingDetails): ?><a class="stat-tile" href="<?= e(bo_url('venue', 'details?gc=2')) ?>"><i class="bi bi-hourglass-split"></i><span class="v"><?= $pendingDetails ?></span><span class="l">Waiting for approval</span></a><?php endif; ?>
    </div>
    <?php if ($isMosque): ?>
    <div class="row g-4 mb-4">
      <div class="col-md-6"><div class="card bo-card h-100"><div class="card-body">
        <h2 class="h6"><i class="bi bi-clock me-1"></i> Daily prayer times</h2>
        <?php if ($daysAhead >= 14): ?><p class="small text-success mb-2"><i class="bi bi-check-circle-fill"></i> Published until <?= e(fmt_date($lastDate)) ?></p>
        <?php elseif ($daysAhead > 0): ?><p class="small text-warning-emphasis mb-2"><i class="bi bi-exclamation-triangle-fill"></i> Only <?= $daysAhead ?> days left (until <?= e(fmt_date($lastDate)) ?>)</p>
        <?php else: ?><p class="small text-danger mb-2"><i class="bi bi-x-circle-fill"></i> No upcoming times published</p><?php endif; ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(bo_url('venue', 'prayer')) ?>">Manage prayer times</a>
      </div></div></div>
      <div class="col-md-6"><div class="card bo-card h-100"><div class="card-body">
        <h2 class="h6"><i class="bi bi-people me-1"></i> Jumu'ah</h2>
        <?php if ($jCurrent): ?><p class="small text-success mb-2"><i class="bi bi-check-circle-fill"></i> <?= plural($jCurrent, 'session') ?> current<?= $jUntil ? ', until ' . e(fmt_date($jUntil)) : '' ?></p><?php else: ?><p class="small text-danger mb-2"><i class="bi bi-x-circle-fill"></i> No current Jumu'ah schedule</p><?php endif; ?>
        <a class="btn btn-sm btn-outline-primary" href="<?= e(bo_url('venue', 'jummah')) ?>">Manage Jumu'ah</a>
      </div></div></div>
    </div>
    <?php endif; ?>
    <div class="row g-4">
      <div class="col-md-6"><div class="card bo-card h-100"><div class="card-header d-flex"><h2 class="h6 m-0">Upcoming events</h2><a class="ms-auto small" href="<?= e(bo_url('venue', 'detail-edit?gc=1')) ?>">Add</a></div>
        <ul class="list-group list-group-flush small"><?php if (!$upcoming): ?><li class="list-group-item text-muted">No upcoming events.</li><?php endif; foreach ($upcoming as $u): ?><li class="list-group-item d-flex"><a href="<?= e(bo_url('venue', 'detail-edit?id=' . (int)$u['detail_id'])) ?>"><?= e($u['details_title']) ?></a><span class="ms-auto text-muted"><?= e(fmt_date($u['start_date'], 'j M')) ?></span></li><?php endforeach; ?></ul></div></div>
      <div class="col-md-6"><div class="card bo-card h-100"><div class="card-header"><h2 class="h6 m-0">Recent activity</h2></div>
        <ul class="list-group list-group-flush small"><?php if (!$activity): ?><li class="list-group-item text-muted">Nothing yet.</li><?php endif; foreach ($activity as $a): ?><li class="list-group-item d-flex gap-2"><span class="text-muted text-nowrap"><?= e(date('j M H:i', strtotime($a['created_at']))) ?></span><span><?= e(str_replace(array('.', '_'), array(' · ', ' '), $a['action'])) ?><?= $a['actor_type'] === 'admin' ? ' <span class="text-muted">(The Hashir team)</span>' : '' ?></span></li><?php endforeach; ?></ul></div></div>
    </div>
  </div>
</div>
<?php bo_end();
