<?php
/** /manage/detail-edit?id= | ?gc=&venue= */
require_admin('details');
$id = int_param('id');
$d = $id ? db_one('SELECT * FROM th_details WHERE detail_id = ?', 'i', array($id)) : null;
if ($id && !$d) {
    flash('danger', 'Record not found.');
    bo_redirect('admin', 'details');
}
if (is_post()) {
    require_post_csrf();
    if (post('action') === 'delete' && $d) {
        db_exec('DELETE FROM th_details WHERE detail_id = ?', 'i', array($id));
        log_me('detail.delete', 'detail', $id, venue_log_subset($d), null);
        flash('success', 'Deleted.');
        bo_redirect('admin', 'details?gc=' . (int)$d['grand_category_id']);
    }
    $newId = detail_save($id, 0, true);
    bo_redirect('admin', $newId ? 'detail-edit?id=' . $newId : 'detail-edit?' . ($id ? 'id=' . $id : 'gc=' . post_int('grand_category_id', 2)));
}
if (!empty($_SESSION['form_old'])) {
    $o = $_SESSION['form_old'];
    unset($_SESSION['form_old']);
    $d = array_merge($d ? $d : array(), array('details_title' => isset($o['details_title']) ? $o['details_title'] : '', 'description' => isset($o['description']) ? $o['description'] : '', 'venue_id' => isset($o['venue_id']) ? $o['venue_id'] : ''));
}
$gc = $d && !empty($d['grand_category_id']) ? (int)$d['grand_category_id'] : (in_array(int_param('gc', 2), array(1, 2, 3), true) ? int_param('gc', 2) : 2);
$g = grand_info($gc);
bo_start('admin', $id ? 'Edit ' . strtolower($g['single']) : 'Add ' . strtolower($g['single']), array('editor' => true, 'nav' => 'details?gc=' . $gc));
if ($d && $id && (int)$d['status_verify'] === 1): ?><p><a class="btn btn-sm btn-outline-secondary" href="<?= e(url(detail_url($d))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>View on website</a></p><?php endif;
detail_form($d ? $d : array(), true, ADMIN_SELF . ($id ? '?id=' . $id : '?gc=' . $gc), $gc, int_param('venue'));
if ($id): ?>
<form method="post" class="mt-4"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm" data-confirm-click="Delete “<?= e($d['details_title']) ?>” permanently? This cannot be undone."><i class="bi bi-trash me-1"></i>Delete</button></form>
<?php endif;
bo_end();
