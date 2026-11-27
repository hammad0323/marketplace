<?php
/** /manage/prayer?venue= — prayer schedule of any mosque. */
require_admin('prayer');
$venueId = int_param('venue');
$v = $venueId ? get_venue_any($venueId) : null;
if ($v) {
    prayer_handle('admin', $venueId, ADMIN_SELF);
}
bo_start('admin', 'Prayer times');
?>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-4">
  <div style="min-width:320px"><label class="form-label small mb-1">Mosque</label><?php bo_venue_select('venue', $venueId, '1', false, 'pvenue', 'Search mosques…'); ?></div>
  <button class="btn btn-outline-primary">Open</button>
</form>
<?php if ($v): prayer_manager('admin', $venueId, ADMIN_SELF); else:
    $today = date('Y-m-d');
    $withTimes = db_all('SELECT v.venue_id, v.venue_title, c.city_title, MAX(p.georgian_date) last_date, SUM(p.georgian_date >= ?) upcoming
        FROM prayer_timings p JOIN th_venue v ON v.venue_id = p.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id GROUP BY v.venue_id ORDER BY last_date DESC', 's', array($today));
?>
<div class="card bo-card">
  <div class="card-header"><h2 class="h6 m-0">Mosques with daily prayer times</h2></div>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Mosque</th><th>Times until</th><th>Days ahead</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($withTimes as $r): ?>
      <tr><td><?= e(trim($r['venue_title'])) ?> <span class="small text-muted"><?= e($r['city_title']) ?></span></td><td><?= e(fmt_date($r['last_date'])) ?></td><td><?= (int)$r['upcoming'] < 14 ? '<span class="text-danger fw-semibold">' . (int)$r['upcoming'] . '</span>' : (int)$r['upcoming'] ?></td>
      <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(ADMIN_SELF . '?venue=' . (int)$r['venue_id']) ?>">Manage</a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>
<?php endif;
bo_end();
