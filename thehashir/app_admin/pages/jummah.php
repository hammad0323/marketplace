<?php
/** /manage/jummah?venue= */
require_admin('prayer');
$venueId = int_param('venue');
$v = $venueId ? get_venue_any($venueId) : null;
if ($v) {
    jummah_handle('admin', $venueId, ADMIN_SELF);
}
bo_start('admin', 'Jumu\'ah');
?>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-4">
  <div style="min-width:320px"><label class="form-label small mb-1">Mosque</label><?php bo_venue_select('venue', $venueId, '', false, 'jvenue', 'Search mosques…'); ?></div>
  <button class="btn btn-outline-primary">Open</button>
</form>
<?php if ($v): jummah_manager('admin', $venueId, ADMIN_SELF); else:
    $today = date('Y-m-d');
    $n = (int)db_val('SELECT COUNT(DISTINCT venue_id) FROM th_jummah_prayers WHERE from_date <= ? AND to_date >= ?', 'ss', array($today, $today));
    $ending = db_all('SELECT v.venue_id, v.venue_title, c.city_title, MAX(j.to_date) last_to FROM th_jummah_prayers j JOIN th_venue v ON v.venue_id = j.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id
        GROUP BY v.venue_id HAVING last_to BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY) ORDER BY last_to LIMIT 50', 'ss', array($today, $today));
?>
<p class="text-muted"><?= number_format($n) ?> mosques have a current Jumu'ah schedule.</p>
<div class="card bo-card">
  <div class="card-header"><h2 class="h6 m-0">Schedules ending in the next 30 days</h2></div>
  <ul class="list-group list-group-flush">
    <?php if (!$ending): ?><li class="list-group-item text-muted small">None.</li><?php endif; ?>
    <?php foreach ($ending as $r): ?><li class="list-group-item d-flex align-items-center"><span><?= e(trim($r['venue_title'])) ?> <span class="small text-muted"><?= e($r['city_title']) ?></span></span><span class="ms-auto small me-3">until <?= e(fmt_date($r['last_to'])) ?></span><a class="btn btn-sm btn-outline-primary" href="<?= e(ADMIN_SELF . '?venue=' . (int)$r['venue_id']) ?>">Manage</a></li><?php endforeach; ?>
  </ul>
</div>
<?php endif;
bo_end();
