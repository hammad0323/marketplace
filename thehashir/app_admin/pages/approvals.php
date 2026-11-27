<?php
/** /manage/approvals — pending profiles, profile changes and portal submissions. */
require_admin('approvals');
if (is_post()) {
    require_post_csrf();
    $what = post('what');
    $id = post_int('id');
    $ok = post('decision') === 'approve';
    if ($what === 'detail' && $id) {
        $old = db_one('SELECT * FROM th_details WHERE detail_id = ?', 'i', array($id));
        if ($old) {
            if ($ok) {
                db_exec('UPDATE th_details SET status_verify = 1 WHERE detail_id = ?', 'i', array($id));
                log_me('detail.approve', 'detail', $id);
                flash('success', '“' . $old['details_title'] . '” approved and published.');
            } else {
                // Rejecting keeps the record unpublished (nothing is deleted) and notes the reason
                log_me('detail.reject', 'detail', $id, null, array('reason' => post('reason')));
                flash('success', '“' . $old['details_title'] . '” stays unpublished.');
            }
        }
    } elseif ($what === 'venue' && $id) {
        if ($ok) {
            db_exec('UPDATE th_venue SET verify_status = 1 WHERE venue_id = ?', 'i', array($id));
            log_me('venue.verify', 'venue', $id);
            coords_cache_clear();
            flash('success', 'Profile verified and published.');
        }
    } elseif ($what === 'changes' && $id) {
        $p = pending_profile($id);
        if ($p) {
            if ($ok) {
                $old = db_one('SELECT * FROM th_venue WHERE venue_id = ?', 'i', array($id));
                venue_apply($id, $p['data']);
                log_me('venue.approve_changes', 'venue', $id, venue_log_subset($old), venue_log_subset($p['data']));
                flash('success', 'Profile changes applied.');
            } else {
                log_me('venue.reject_changes', 'venue', $id, null, array('reason' => post('reason')));
                flash('success', 'Profile changes discarded.');
            }
            storage_delete('pending/venue-' . $id . '.json');
        }
    }
    bo_redirect('admin', 'approvals');
}
$details = db_all('SELECT d.detail_id, d.details_title, d.grand_category_id, d.update_date, d.venue_id, v.venue_title FROM th_details d LEFT JOIN th_venue v ON v.venue_id = d.venue_id WHERE d.status_verify = 0 ORDER BY d.update_date DESC LIMIT 200');
$venues = db_all('SELECT v.venue_id, v.venue_title, v.role_id, v.update_date, c.city_title FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id WHERE v.verify_status = 0 ORDER BY v.update_date DESC LIMIT 200');
$changes = array();
foreach (storage_list('pending') as $k) {
    $p = storage_read('pending/' . $k . '.json', null);
    if ($p && !empty($p['venue_id'])) {
        $p['current'] = db_one('SELECT * FROM th_venue WHERE venue_id = ?', 'i', array((int)$p['venue_id']));
        if ($p['current']) {
            $changes[] = $p;
        }
    }
}
function approve_buttons($what, $id, $canReject = true)
{
    echo '<form method="post" class="d-inline-flex gap-1">' . csrf_field() . '<input type="hidden" name="what" value="' . e($what) . '"><input type="hidden" name="id" value="' . (int)$id . '">';
    echo '<button class="btn btn-sm btn-success" name="decision" value="approve"><i class="bi bi-check2"></i> Approve</button>';
    if ($canReject) {
        echo '<button class="btn btn-sm btn-outline-secondary" name="decision" value="reject" data-confirm-click="Reject this submission?">Reject</button>';
    }
    echo '</form>';
}
bo_start('admin', 'Approvals');
?>
<div class="card bo-card mb-4">
  <div class="card-header"><h2 class="h6 m-0">Profile changes from the portal <span class="badge text-bg-secondary"><?= count($changes) ?></span></h2></div>
  <?php if (!$changes): ?><div class="card-body text-muted small">Nothing waiting.</div><?php endif; ?>
  <?php foreach ($changes as $p): $cur = $p['current']; ?>
  <div class="card-body border-top">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <strong><?= e(trim($cur['venue_title'])) ?></strong><span class="small text-muted">submitted <?= e($p['submitted_at']) ?></span>
      <a class="small" href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$p['venue_id'])) ?>">Open profile</a>
      <span class="ms-auto"><?php approve_buttons('changes', $p['venue_id']); ?></span>
    </div>
    <div class="table-responsive"><table class="table table-sm small diff-table mb-0"><thead><tr><th>Field</th><th>Current</th><th>New</th></tr></thead><tbody>
    <?php foreach ($p['data'] as $k => $val):
        if ($k === '__coords') { $prev = coords_from_iframe($cur['address_iframe']); $now = $val[0] . ', ' . $val[1]; $was = $prev ? $prev['lat'] . ', ' . $prev['lng'] : ''; if ($prev && abs($prev['lat'] - $val[0]) < 0.000001 && abs($prev['lng'] - $val[1]) < 0.000001) { continue; } echo '<tr><th>Map location</th><td>' . e($was) . '</td><td class="table-warning">' . e($now) . '</td></tr>'; continue; }
        $was = isset($cur[$k]) ? (string)$cur[$k] : '';
        if ((string)$val === $was) { continue; }
        $label = isset(venue_text_fields()[$k]) ? venue_text_fields()[$k][0] : ucwords(str_replace('_', ' ', $k));
        if ($k === 'profile_image' || $k === 'back_image') {
            $folder = $k === 'profile_image' ? 'venue-images' : 'venue-cover';
            echo '<tr><th>' . e($k === 'profile_image' ? 'Logo' : 'Cover') . '</th><td>' . ($was !== '' ? '<img src="' . e(media('images/' . $folder . '/' . $was)) . '" style="max-height:60px" alt="">' : '') . '</td><td class="table-warning"><img src="' . e(media('images/' . $folder . '/' . $val)) . '" style="max-height:60px" alt=""></td></tr>';
            continue;
        }
        if ($k === 'city_id') { $c1 = get_city((int)$was); $c2 = get_city((int)$val); $was = $c1 ? $c1['city_title'] : $was; $val = $c2 ? $c2['city_title'] : $val; $label = 'City'; }
    ?>
      <tr><th><?= e($label) ?></th><td><?= e(excerpt($was, 300)) ?></td><td class="table-warning"><?= e(excerpt($val, 300)) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card bo-card mb-4">
  <div class="card-header"><h2 class="h6 m-0">Programs, services &amp; events waiting <span class="badge text-bg-secondary"><?= count($details) ?></span></h2></div>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <tbody>
    <?php if (!$details): ?><tr><td class="text-muted small">Nothing waiting.</td></tr><?php endif; ?>
    <?php foreach ($details as $d): $g = grand_info($d['grand_category_id']); ?>
      <tr>
        <td><span class="badge text-bg-light"><?= e($g['single']) ?></span></td>
        <td><a href="<?= e(bo_url('admin', 'detail-edit?id=' . (int)$d['detail_id'])) ?>"><?= e($d['details_title']) ?></a><div class="small text-muted"><?= e(trim((string)$d['venue_title'])) ?> · <?= e(fmt_date($d['update_date'])) ?></div></td>
        <td class="text-end"><?php approve_buttons('detail', $d['detail_id']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="card bo-card">
  <div class="card-header"><h2 class="h6 m-0">Unverified profiles <span class="badge text-bg-secondary"><?= count($venues) ?></span></h2></div>
  <div class="table-responsive"><table class="table align-middle mb-0"><tbody>
    <?php if (!$venues): ?><tr><td class="text-muted small">Nothing waiting.</td></tr><?php endif; ?>
    <?php foreach ($venues as $v): ?>
      <tr><td><i class="bi <?= e(role_icon($v['role_id'])) ?> text-muted"></i></td><td><a href="<?= e(bo_url('admin', 'venue-edit?id=' . (int)$v['venue_id'])) ?>"><?= e(trim($v['venue_title'])) ?></a><div class="small text-muted"><?= e($v['city_title']) ?> · updated <?= e(fmt_date($v['update_date'])) ?></div></td><td class="text-end"><?php approve_buttons('venue', $v['venue_id'], false); ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php bo_end();
