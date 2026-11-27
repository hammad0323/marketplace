<?php
/** /manage/logs — activity log (th_display_audit_logs, shared with the display-screen system). */
require_admin('logs');
$actor = str_param('actor', 10);
$q = str_param('q', 60);
$page = max(1, int_param('page', 1));
$per = 50;
$where = array('1=1');
$types = '';
$params = array();
if (in_array($actor, array('admin', 'venue'), true)) {
    $where[] = 'l.actor_type = ?';
    $types .= 's';
    $params[] = $actor;
}
if ($q !== '') {
    $where[] = '(l.action LIKE ? OR l.entity_type LIKE ? OR v.venue_title LIKE ? OR a.admin_name LIKE ?)';
    $types .= 'ssss';
    array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
}
$w = implode(' AND ', $where);
$from = 'FROM th_display_audit_logs l LEFT JOIN th_admin_table a ON a.admin_id = l.admin_id LEFT JOIN th_venue v ON v.venue_id = l.venue_id';
$total = (int)db_val("SELECT COUNT(*) $from WHERE $w", $types, $params);
$rows = db_all("SELECT l.*, a.admin_name, v.venue_title $from WHERE $w ORDER BY l.id DESC LIMIT " . (($page - 1) * $per) . ", $per", $types, $params);
bo_start('admin', 'Activity log');
?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get">
  <select class="form-select" name="actor" style="max-width:180px"><option value="">Everyone</option><option value="admin"<?= sel('admin', $actor) ?>>Admins</option><option value="venue"<?= sel('venue', $actor) ?>>Mosques &amp; organizations</option></select>
  <input class="form-control" style="max-width:280px" name="q" value="<?= e($q) ?>" placeholder="Action, name… e.g. prayer, login">
  <button class="btn btn-outline-primary">Filter</button>
</form>
<div class="card bo-card"><div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
  <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>IP</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $l): ?>
    <tr>
      <td class="text-nowrap"><?= e(date('j M Y H:i', strtotime($l['created_at']))) ?></td>
      <td><?= $l['actor_type'] === 'admin' ? '<i class="bi bi-person-gear me-1"></i>' . e($l['admin_name'] ?: 'Admin #' . $l['admin_id']) : '<i class="bi bi-building me-1"></i>' . e(excerpt($l['venue_title'] ?: 'Venue #' . $l['venue_id'], 40)) ?></td>
      <td><code><?= e($l['action']) ?></code></td>
      <td><?= e($l['entity_type']) ?><?= $l['entity_id'] ? ' #' . (int)$l['entity_id'] : '' ?></td>
      <td class="text-muted"><?= e($l['ip_address']) ?></td>
      <td><?php if ($l['old_data'] || $l['new_data']): ?><button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#log<?= (int)$l['id'] ?>">Details</button><?php endif; ?></td>
    </tr>
    <?php if ($l['old_data'] || $l['new_data']): ?>
    <tr class="collapse" id="log<?= (int)$l['id'] ?>"><td colspan="6"><div class="row g-2"><?php if ($l['old_data']): ?><div class="col-md-6"><div class="small text-muted">Before</div><pre class="log-json"><?= e(json_pretty($l['old_data'])) ?></pre></div><?php endif; ?><?php if ($l['new_data']): ?><div class="col-md-6"><div class="small text-muted">After</div><pre class="log-json"><?= e(json_pretty($l['new_data'])) ?></pre></div><?php endif; ?></div></td></tr>
    <?php endif; ?>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="card-footer"><?php bo_pager($total, $per, $page); ?></div></div>
<?php bo_end();
function json_pretty($s)
{
    $d = json_decode($s, true);
    return $d === null ? $s : mb_substr(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000, 'UTF-8');
}
