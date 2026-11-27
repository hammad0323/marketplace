<?php
/** /manage/details?gc=2|3|1 — programs, services, events */
require_admin('details');
$gc = in_array(int_param('gc', 2), array(1, 2, 3), true) ? int_param('gc', 2) : 2;
$g = grand_info($gc);
if (is_post()) {
    require_post_csrf();
    $ids = post_ids('ids');
    $act = post('bulk');
    if ($ids && in_array($act, array('approve', 'unpublish', 'delete'), true)) {
        list($ph, $t, $vals) = db_in_ints($ids);
        if ($act === 'delete') {
            $old = db_all("SELECT * FROM th_details WHERE detail_id IN ($ph)", $t, $vals);
            db_exec("DELETE FROM th_details WHERE detail_id IN ($ph)", $t, $vals);
            foreach ($old as $o) {
                log_me('detail.delete', 'detail', $o['detail_id'], venue_log_subset($o), null);
            }
            flash('success', plural(count($old), 'record') . ' deleted.');
        } else {
            db_exec("UPDATE th_details SET status_verify = ? WHERE detail_id IN ($ph)", 'i' . $t, array_merge(array($act === 'approve' ? 1 : 0), $vals));
            log_me('detail.bulk_' . $act, 'detail', null, null, array('ids' => $ids));
            flash('success', plural(count($ids), 'record') . ' updated.');
        }
    }
    redirect_back(ADMIN_PATH . '/details?gc=' . $gc);
}
$q = str_param('q', 80);
$cat = int_param('category');
$venue = int_param('venue');
$cityId = int_param('city');
$status = isset($_GET['status']) && $_GET['status'] !== '' ? int_param('status') : null;
$sort = str_param('sort', 10);
$page = max(1, int_param('page', 1));
$per = 30;
$where = array('d.grand_category_id = ?');
$types = 'i';
$params = array($gc);
if ($q !== '') {
    $where[] = '(d.details_title LIKE ? OR v.venue_title LIKE ? OR d.detail_id = ?)';
    $types .= 'ssi';
    array_push($params, '%' . $q . '%', '%' . $q . '%', ctype_digit($q) ? (int)$q : 0);
}
if ($cat) {
    $where[] = 'FIND_IN_SET(?, REPLACE(d.category_id, " ", "")) > 0';
    $types .= 's';
    $params[] = (string)$cat;
}
if ($venue) {
    $where[] = 'd.venue_id = ?';
    $types .= 'i';
    $params[] = $venue;
}
if ($cityId) {
    $where[] = 'v.city_id = ?';
    $types .= 'i';
    $params[] = $cityId;
}
if ($status !== null) {
    $where[] = 'd.status_verify = ?';
    $types .= 'i';
    $params[] = $status;
}
$w = implode(' AND ', $where);
$from = 'FROM th_details d LEFT JOIN th_venue v ON v.venue_id = d.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id';
$total = (int)db_val("SELECT COUNT(*) $from WHERE $w", $types, $params);
$order = $sort === 'title' ? 'd.details_title' : ($sort === 'start' ? 'd.start_date DESC' : 'd.update_date DESC, d.detail_id DESC');
$off = ($page - 1) * $per;
$rows = db_all("SELECT d.detail_id, d.details_title, d.details_url, d.category_id, d.status_verify, d.update_date, d.start_date, d.end_date, d.expire_date, d.grand_category_id, d.profile_image_1, d.venue_id, v.venue_title, c.city_title $from WHERE $w ORDER BY $order LIMIT $off, $per", $types, $params);
$cats = get_categories($gc, false);
bo_start('admin', $g['plural']);
?>
<div class="d-flex flex-wrap gap-2 align-items-end mb-3">
  <form class="d-flex flex-wrap gap-2 align-items-end flex-grow-1" method="get">
    <input type="hidden" name="gc" value="<?= $gc ?>">
    <div><label class="form-label small mb-1">Search</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Title, mosque, ID"></div>
    <div><label class="form-label small mb-1">Category</label><select class="form-select" name="category"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>"<?= sel($c['category_id'], $cat) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div style="min-width:220px"><label class="form-label small mb-1">Mosque / organization</label><?php bo_venue_select('venue', $venue, '', false, 'fvenue', 'Any'); ?></div>
    <div style="min-width:180px"><label class="form-label small mb-1">City</label><?php bo_city_select('city', $cityId, false, 'fcity'); ?></div>
    <div><label class="form-label small mb-1">Status</label><select class="form-select" name="status"><option value="">All</option><option value="1"<?= sel(1, $status) ?>>Published</option><option value="0"<?= $status === 0 ? ' selected' : '' ?>>Pending</option></select></div>
    <div><label class="form-label small mb-1">Sort</label><select class="form-select" name="sort"><option value="">Recently updated</option><option value="title"<?= sel('title', $sort) ?>>Title</option><option value="start"<?= sel('start', $sort) ?>>Start date</option></select></div>
    <button class="btn btn-outline-primary">Filter</button>
  </form>
  <a class="btn btn-primary" href="<?= e(bo_url('admin', 'detail-edit?gc=' . $gc . ($venue ? '&venue=' . $venue : ''))) ?>"><i class="bi bi-plus-lg me-1"></i>Add <?= e(strtolower($g['single'])) ?></a>
</div>
<form method="post" class="card bo-card">
  <?= csrf_field() ?>
  <div class="card-header d-flex flex-wrap align-items-center gap-2">
    <span class="small text-muted"><?= number_format($total) ?> found</span>
    <div class="ms-auto d-flex gap-2"><select class="form-select form-select-sm" name="bulk" aria-label="Bulk action"><option value="">Bulk action…</option><option value="approve">Approve &amp; publish</option><option value="unpublish">Unpublish</option><option value="delete">Delete permanently</option></select><button class="btn btn-sm btn-outline-secondary" data-confirm-click="Apply to the selected records? Deleting cannot be undone.">Apply</button></div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0 bo-table">
      <thead><tr><th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all aria-label="Select all"></th><th>Title</th><th>Mosque / organization</th><th>Category</th><?php if ($gc === 1): ?><th>Dates</th><?php endif; ?><th>Status</th><th>Updated</th><th></th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Nothing found.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): $rc = detail_categories($r); ?>
        <tr>
          <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int)$r['detail_id'] ?>" aria-label="Select"></td>
          <td><a class="fw-semibold" href="<?= e(bo_url('admin', 'detail-edit?id=' . (int)$r['detail_id'])) ?>"><?= e(trim($r['details_title'])) ?></a><div class="small text-muted">#<?= (int)$r['detail_id'] ?></div></td>
          <td class="small"><?php if ($r['venue_id']): ?><a href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$r['venue_id'])) ?>"><?= e(trim((string)$r['venue_title'])) ?></a><div class="text-muted"><?= e($r['city_title']) ?></div><?php endif; ?></td>
          <td class="small"><?= e(implode(', ', array_map(function ($c) { return $c['name']; }, $rc))) ?></td>
          <?php if ($gc === 1): ?><td class="small text-nowrap"><?= e(fmt_date($r['start_date'])) ?><?= valid_date($r['end_date']) && $r['end_date'] !== $r['start_date'] ? ' – ' . e(fmt_date($r['end_date'])) : '' ?><?= valid_date($r['expire_date']) && $r['expire_date'] < date('Y-m-d') ? '<br><span class="badge text-bg-secondary">Ended</span>' : '' ?></td><?php endif; ?>
          <td><?= status_badge((int)$r['status_verify'] === 1) ?></td>
          <td class="small text-muted text-nowrap"><?= e(fmt_date($r['update_date'])) ?></td>
          <td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'detail-edit?id=' . (int)$r['detail_id'])) ?>"><i class="bi bi-pencil"></i></a><?php if ((int)$r['status_verify'] === 1): ?> <a class="btn btn-sm btn-light" href="<?= e(url(detail_url($r))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer"><?php bo_pager($total, $per, $page); ?></div>
</form>
<?php bo_end();
