<?php
/** /portal/details?gc= — own programs / services / events */
$me = require_venue();
$id = (int)$me['id'];
$gc = in_array(int_param('gc', 2), array(1, 2, 3), true) ? int_param('gc', 2) : 2;
$g = grand_info($gc);
if (is_post()) {
    require_post_csrf();
    $did = post_int('detail_id');
    $old = db_one('SELECT * FROM th_details WHERE detail_id = ? AND venue_id = ?', 'ii', array($did, $id));
    if ($old && post('action') === 'delete') {
        db_exec('DELETE FROM th_details WHERE detail_id = ? AND venue_id = ?', 'ii', array($did, $id));
        log_me('detail.delete', 'detail', $did, venue_log_subset($old), null);
        flash('success', '“' . $old['details_title'] . '” deleted.');
    }
    bo_redirect('venue', 'details?gc=' . $gc);
}
$rows = db_all('SELECT detail_id, details_title, details_url, category_id, status_verify, update_date, start_date, end_date, expire_date, grand_category_id FROM th_details WHERE venue_id = ? AND grand_category_id = ? ORDER BY update_date DESC, detail_id DESC', 'ii', array($id, $gc));
bo_start('venue', $g['plural']);
?>
<div class="d-flex mb-3"><p class="text-muted mb-0"><?= setting('approve_details') ? 'New and edited ' . strtolower($g['plural']) . ' are reviewed before they go live.' : '' ?></p><a class="btn btn-primary ms-auto" href="<?= e(bo_url('venue', 'detail-edit?gc=' . $gc)) ?>"><i class="bi bi-plus-lg me-1"></i>Add <?= e(strtolower($g['single'])) ?></a></div>
<div class="card bo-card"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th>Title</th><th>Category</th><?php if ($gc === 1): ?><th>Dates</th><?php endif; ?><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">No <?= e(strtolower($g['plural'])) ?> yet.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a class="fw-semibold" href="<?= e(bo_url('venue', 'detail-edit?id=' . (int)$r['detail_id'])) ?>"><?= e($r['details_title']) ?></a><div class="small text-muted">Updated <?= e(fmt_date($r['update_date'])) ?></div></td>
      <td class="small"><?= e(implode(', ', array_map(function ($c) { return $c['name']; }, detail_categories($r)))) ?></td>
      <?php if ($gc === 1): ?><td class="small"><?= e(fmt_date($r['start_date'])) ?><?= valid_date($r['end_date']) && $r['end_date'] !== $r['start_date'] ? ' – ' . e(fmt_date($r['end_date'])) : '' ?></td><?php endif; ?>
      <td><?= status_badge((int)$r['status_verify'] === 1, 'Published', 'Waiting for approval') ?></td>
      <td class="text-end text-nowrap">
        <a class="btn btn-sm btn-light" href="<?= e(bo_url('venue', 'detail-edit?id=' . (int)$r['detail_id'])) ?>"><i class="bi bi-pencil"></i></a>
        <?php if ((int)$r['status_verify'] === 1): ?><a class="btn btn-sm btn-light" href="<?= e(url(detail_url($r))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
        <button class="btn btn-sm btn-light text-danger" type="button" data-post-action='<?= e(json_encode(array('action' => 'delete', 'detail_id' => (int)$r['detail_id']))) ?>' data-confirm-text="Delete “<?= e($r['details_title']) ?>”? This cannot be undone."><i class="bi bi-trash"></i></button>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php bo_end();
