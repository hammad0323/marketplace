<?php
/** /portal/detail-edit?id= | ?gc= — only records of the signed-in venue can be opened or saved. */
$me = require_venue();
$id = (int)$me['id'];
$did = int_param('id');
$d = $did ? db_one('SELECT * FROM th_details WHERE detail_id = ? AND venue_id = ?', 'ii', array($did, $id)) : null;
if ($did && !$d) {
    http_response_code(404);
    flash('danger', 'That record does not exist or does not belong to your profile.');
    bo_redirect('venue', 'details');
}
if (is_post()) {
    require_post_csrf();
    $newId = detail_save($did, $id, false);
    bo_redirect('venue', $newId ? 'details?gc=' . post_int('grand_category_id', 2) : 'detail-edit?' . ($did ? 'id=' . $did : 'gc=' . post_int('grand_category_id', 2)));
}
$gc = $d ? (int)$d['grand_category_id'] : (in_array(int_param('gc', 2), array(1, 2, 3), true) ? int_param('gc', 2) : 2);
$g = grand_info($gc);
bo_start('venue', ($did ? 'Edit ' : 'Add ') . strtolower($g['single']), array('editor' => true, 'nav' => 'details?gc=' . $gc));
detail_form($d ? $d : array(), false, PORTAL_SELF . ($did ? '?id=' . $did : '?gc=' . $gc), $gc, $id);
bo_end();
