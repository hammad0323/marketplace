<?php
/** /manage/venues?type=mosque|org — list, search, filter. */
require_admin('venues');
$type = str_param('type', 10) === 'org' ? 'org' : 'mosque';
if (is_post()) {
    require_post_csrf();
    $ids = post_ids('ids');
    $act = post('bulk');
    if ($ids && in_array($act, array('verify', 'unverify'), true)) {
        list($ph, $t, $vals) = db_in_ints($ids);
        db_exec("UPDATE th_venue SET verify_status = ? WHERE venue_id IN ($ph)", 'i' . $t, array_merge(array($act === 'verify' ? 1 : 0), $vals));
        log_me('venue.bulk_' . $act, 'venue', null, null, array('ids' => $ids));
        coords_cache_clear();
        flash('success', plural(count($ids), 'profile') . ' ' . ($act === 'verify' ? 'verified and published' : 'hidden from the website') . '.');
    }
    redirect_back(ADMIN_PATH . '/venues?type=' . $type);
}
$q = str_param('q', 80);
$status = isset($_GET['status']) && $_GET['status'] !== '' ? int_param('status') : null;
$role = int_param('role');
$cityId = int_param('city');
$sort = str_param('sort', 10);
$page = max(1, int_param('page', 1));
$per = 30;
$where = array($type === 'mosque' ? 'v.role_id = 1' : 'v.role_id <> 1');
$types = '';
$params = array();
if ($q !== '') {
    $where[] = '(v.venue_title LIKE ? OR v.venue_aka LIKE ? OR v.street_address LIKE ? OR v.postal_code LIKE ? OR c.city_title LIKE ? OR v.venue_id = ?)';
    $like = '%' . $q . '%';
    $types .= 'sssssi';
    array_push($params, $like, $like, $like, $like, $like, ctype_digit($q) ? (int)$q : 0);
}
if ($status !== null) {
    $where[] = 'v.verify_status = ?';
    $types .= 'i';
    $params[] = $status;
}
if ($role && $type === 'org') {
    $where[] = 'v.role_id = ?';
    $types .= 'i';
    $params[] = $role;
}
if ($cityId) {
    $where[] = 'v.city_id = ?';
    $types .= 'i';
    $params[] = $cityId;
}
$w = implode(' AND ', $where);
$total = (int)db_val("SELECT COUNT(*) FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id WHERE $w", $types, $params);
$order = $sort === 'updated' ? 'v.update_date DESC, v.venue_id DESC' : ($sort === 'id' ? 'v.venue_id DESC' : 'v.venue_title ASC');
$off = ($page - 1) * $per;
$rows = db_all("SELECT v.venue_id, v.venue_title, v.venue_aka, v.role_id, v.verify_status, v.update_date, v.profile_image, v.username, v.address_iframe, v.url,
        c.city_title, s.state_title, r.role_title,
        (SELECT COUNT(*) FROM th_details d WHERE d.venue_id = v.venue_id) n_details
    FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN organization_roles r ON r.role_id = v.role_id
    WHERE $w ORDER BY $order LIMIT $off, $per", $types, $params);
$roles = db_all('SELECT role_id, role_title FROM organization_roles WHERE role_id <> 1 ORDER BY role_id');
bo_start('admin', $type === 'mosque' ? 'Mosques' : 'Organizations');
?>
<div class="d-flex flex-wrap gap-2 align-items-end mb-3">
  <form class="d-flex flex-wrap gap-2 align-items-end flex-grow-1" method="get">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <div><label class="form-label small mb-1">Search</label><input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name, AKA, address, postal code, ID"></div>
    <div style="min-width:200px"><label class="form-label small mb-1">City</label><?php bo_city_select('city', $cityId); ?></div>
    <?php if ($type === 'org'): ?><div><label class="form-label small mb-1">Type</label><select class="form-select" name="role"><option value="">All types</option><?php foreach ($roles as $r): ?><option value="<?= (int)$r['role_id'] ?>"<?= sel($r['role_id'], $role) ?>><?= e($r['role_title']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <div><label class="form-label small mb-1">Status</label><select class="form-select" name="status"><option value="">All</option><option value="1"<?= sel(1, $status) ?>>Verified</option><option value="0"<?= $status === 0 ? ' selected' : '' ?>>Pending / hidden</option></select></div>
    <div><label class="form-label small mb-1">Sort</label><select class="form-select" name="sort"><option value="">Name</option><option value="updated"<?= sel('updated', $sort) ?>>Recently updated</option><option value="id"<?= sel('id', $sort) ?>>Newest</option></select></div>
    <button class="btn btn-outline-primary">Filter</button>
  </form>
  <a class="btn btn-primary" href="<?= e(bo_url('admin', 'venue-edit?type=' . $type)) ?>"><i class="bi bi-plus-lg me-1"></i>Add <?= $type === 'mosque' ? 'mosque' : 'organization' ?></a>
</div>
<form method="post" class="card bo-card">
  <?= csrf_field() ?>
  <div class="card-header d-flex flex-wrap align-items-center gap-2">
    <span class="small text-muted"><?= number_format($total) ?> found</span>
    <div class="ms-auto d-flex gap-2"><select class="form-select form-select-sm" name="bulk" aria-label="Bulk action"><option value="">Bulk action…</option><option value="verify">Verify &amp; publish</option><option value="unverify">Hide from website</option></select><button class="btn btn-sm btn-outline-secondary" data-confirm-click="Apply to the selected profiles?">Apply</button></div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle mb-0 bo-table">
      <thead><tr><th style="width:32px"><input class="form-check-input" type="checkbox" data-check-all aria-label="Select all"></th><th>Name</th><th>Location</th><?php if ($type === 'org'): ?><th>Type</th><?php endif; ?><th>Listings</th><th>Status</th><th>Updated</th><th></th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">No profiles match.</td></tr><?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int)$r['venue_id'] ?>" aria-label="Select"></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <span class="mini-logo"><?php img_or_initials(venue_logo($r), '', $r['venue_title']); ?></span>
              <div><a class="fw-semibold" href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$r['venue_id'])) ?>"><?= e(trim($r['venue_title'])) ?></a>
              <div class="small text-muted">#<?= (int)$r['venue_id'] ?><?= $r['venue_aka'] ? ' · ' . e(excerpt($r['venue_aka'], 40)) : '' ?><?= $r['username'] ? ' · <i class="bi bi-person-badge" title="Has portal account"></i>' : '' ?><?= coords_from_iframe($r['address_iframe']) ? ' · <i class="bi bi-geo-alt-fill text-success" title="Map location set"></i>' : '' ?></div></div>
            </div>
          </td>
          <td class="small"><?= e(trim($r['city_title'] . ', ' . $r['state_title'], ', ')) ?></td>
          <?php if ($type === 'org'): ?><td class="small"><?= e($r['role_title']) ?></td><?php endif; ?>
          <td class="small"><?= (int)$r['n_details'] ?></td>
          <td><?= status_badge((int)$r['verify_status'] === 1, 'Verified', 'Hidden') ?></td>
          <td class="small text-muted text-nowrap"><?= e(fmt_date($r['update_date'])) ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$r['venue_id'])) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
            <?php if ((int)$r['role_id'] === 1): ?><a class="btn btn-sm btn-light" href="<?= e(bo_url('admin', 'prayer?venue=' . (int)$r['venue_id'])) ?>" title="Prayer times"><i class="bi bi-clock"></i></a><?php endif; ?>
            <?php if ((int)$r['verify_status'] === 1): ?><a class="btn btn-sm btn-light" href="<?= e(url(venue_url($r))) ?>" target="_blank" rel="noopener" title="View on website"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer"><?php bo_pager($total, $per, $page); ?></div>
</form>
<?php bo_end();
