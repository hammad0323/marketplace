<?php
/** /portal/announcements — own announcements (th_announcement). */
$me = require_venue();
$id = (int)$me['id'];
if (is_post()) {
    require_post_csrf();
    if (post('action') === 'delete') {
        $a = db_one('SELECT * FROM th_announcement WHERE news_id = ? AND venue_id = ?', 'ii', array(post_int('news_id'), $id));
        if ($a) {
            db_exec('DELETE FROM th_announcement WHERE news_id = ? AND venue_id = ?', 'ii', array((int)$a['news_id'], $id));
            log_me('announcement.delete', 'announcement', $a['news_id'], venue_log_subset($a), null);
            flash('success', 'Announcement deleted.');
        }
    } else {
        announcement_save(post_int('news_id'), $id);
    }
    bo_redirect('venue', 'announcements');
}
$edit = isset($_GET['edit']) ? db_one('SELECT * FROM th_announcement WHERE news_id = ? AND venue_id = ?', 'ii', array(int_param('edit'), $id)) : null;
$rows = db_all('SELECT * FROM th_announcement WHERE venue_id = ? ORDER BY created_date DESC, news_id DESC LIMIT 100', 'i', array($id));
bo_start('venue', 'Announcements', array('editor' => true));
?>
<div class="row g-4">
  <div class="col-xl-5"><?php announcement_form($edit, PORTAL_SELF, false); ?><p class="small text-muted mt-2">Announcements show on your public profile for <?= (int)ANNOUNCEMENT_MAX_AGE_DAYS ?> days.</p></div>
  <div class="col-xl-7"><div class="card bo-card"><div class="table-responsive"><table class="table align-middle mb-0"><tbody>
    <?php if (!$rows): ?><tr><td class="text-muted small p-3">No announcements yet.</td></tr><?php endif; ?>
    <?php foreach ($rows as $a): ?>
      <tr><td><strong><?= e(clean_text($a['news_title'])) ?></strong><div class="small text-muted">Posted <?= e(fmt_date($a['created_date'])) ?></div></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-light" href="<?= e(PORTAL_SELF . '?edit=' . (int)$a['news_id']) ?>"><i class="bi bi-pencil"></i></a> <button class="btn btn-sm btn-light text-danger" type="button" data-post-action='<?= e(json_encode(array('action' => 'delete', 'news_id' => (int)$a['news_id']))) ?>' data-confirm-text="Delete this announcement?"><i class="bi bi-trash"></i></button></td></tr>
    <?php endforeach; ?>
  </tbody></table></div></div></div>
</div>
<?php bo_end();
